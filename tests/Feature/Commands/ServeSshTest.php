<?php

declare(strict_types=1);

use App\Dto\SshPublicKey;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use phpseclib4\Crypt\Common\PrivateKey;
use phpseclib4\Crypt\EC;
use phpseclib4\Crypt\RSA;
use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;

beforeEach(function (): void {
    Loop::set(new StreamSelectLoop);

    $this->keyPath = sys_get_temp_dir().'/devnull-ssh-'.Str::random();

    config(['ssh.key_path' => $this->keyPath, 'ssh.lookup_token' => 'secret-token']);
});

/**
 * Sends the key owner lookups of `ssh:serve` to the real endpoint, as they would go over HTTP.
 */
function lookUpKeyOwnersOnTheWebApp(): void
{
    Http::fake(function (Request $request) {
        $response = test()->withHeader('Authorization', $request->header('Authorization')[0] ?? '')->getJson($request->url());

        return Http::response($response->json(), $response->status());
    });
}

afterEach(function (): void {
    File::deleteDirectory($this->keyPath);
});

/**
 * Serves SSH until the client script exits, and returns what the client saw.
 */
function serveSshTo(PrivateKey ...$privateKeys): string
{
    $port = (int) parse_url((string) stream_socket_get_name($socket = stream_socket_server('tcp://127.0.0.1:0'), false), PHP_URL_PORT);
    fclose($socket);

    $keyFiles = [];

    foreach ($privateKeys as $index => $privateKey) {
        file_put_contents($keyFiles[] = test()->keyPath."-client-{$index}", $privateKey->toString('OpenSSH'));
    }

    $client = proc_open(
        [PHP_BINARY, base_path('tests/Fixtures/ssh/client.php'), (string) $port, ...$keyFiles],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        env_vars: [...getenv(), 'XDEBUG_MODE' => 'off'],
    );

    Loop::addPeriodicTimer(0.05, function () use ($client): void {
        if (! proc_get_status($client)['running']) {
            Loop::stop();
        }
    });

    Loop::addTimer(20, Loop::stop(...));

    test()->artisan('ssh:serve', ['--host' => '127.0.0.1', '--port' => $port])
        ->expectsOutputToContain("Serving SSH on 127.0.0.1:{$port}.")
        ->assertSuccessful()
        ->run();

    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    proc_close($client);
    array_map(unlink(...), $keyFiles);

    return $output;
}

function mirrorSshKey(User $user, PrivateKey $privateKey): void
{
    SshKey::factory()->for($user)->withPublicKey(SshPublicKey::fromOpenSsh($privateKey->getPublicKey()->toString('OpenSSH')))->create();
}

it('serves the terminal app over ssh', function (): void {
    lookUpKeyOwnersOnTheWebApp();
    $key = EC::createKey('Ed25519');
    mirrorSshKey(User::factory()->create(['username' => 'taylor']), $key);

    $output = serveSshTo($key);

    expect($output)
        ->toContain('Hello, @taylor.')
        ->toContain('Your terminal is 100x30.')
        ->toContain('Your terminal is 120x40.')
        ->toContain("\e[?25h\e[?1049l")
        ->toContain('[shell closed with 0]')
        ->toContain('devnull.sh is interactive only, connect without a command.')
        ->toContain('[exec closed with 1]')
        ->toContain('Hello, stranger.')
        ->not->toContain('[stranger rejected]');

    Http::assertSentCount(2);
});

it('logs in with an rsa key mirrored from github', function (): void {
    lookUpKeyOwnersOnTheWebApp();
    $key = RSA::createKey(2048);
    mirrorSshKey(User::factory()->create(['username' => 'taylor']), $key);

    expect(serveSshTo($key))->toContain('Hello, @taylor.');
});

it('lets the client offer its next key after rejecting an unknown one', function (): void {
    lookUpKeyOwnersOnTheWebApp();
    $key = EC::createKey('Ed25519');
    mirrorSshKey(User::factory()->create(['username' => 'taylor']), $key);

    expect(serveSshTo(EC::createKey('Ed25519'), $key))
        ->toContain('[key rejected]')
        ->toContain('Hello, @taylor.');
});

it('rejects an unknown key so the client can connect as a stranger', function (): void {
    lookUpKeyOwnersOnTheWebApp();
    mirrorSshKey(User::factory()->create(), EC::createKey('Ed25519'));

    expect(serveSshTo(EC::createKey('Ed25519')))
        ->toContain('[key rejected]')
        ->toContain('Hello, stranger.')
        ->not->toContain('Hello, @');
});

it('rejects keys devnull does not accept without looking them up', function (): void {
    lookUpKeyOwnersOnTheWebApp();

    expect(serveSshTo(RSA::createKey(1024)))
        ->toContain('[key rejected]')
        ->toContain('Hello, stranger.');

    Http::assertSentCount(1);
});

it('disconnects a client that offers too many keys', function (): void {
    lookUpKeyOwnersOnTheWebApp();

    $output = serveSshTo(...array_map(fn (): PrivateKey => EC::createKey('Ed25519'), range(1, 7)));

    expect(mb_substr_count($output, '[key rejected]'))->toBe(6)
        ->and($output)->toContain('[disconnected]')
        ->and($output)->toContain('Hello, stranger.');
});

it('connects as a stranger when key owners cannot be looked up, reporting it once a minute', function (): void {
    Exceptions::fake();

    $lookups = 0;
    Http::fake(function () use (&$lookups) {
        return $lookups++ === 0 ? Http::response(['username' => null]) : Http::response(status: 500);
    });

    $key = EC::createKey('Ed25519');
    mirrorSshKey(User::factory()->create(['username' => 'taylor']), $key);

    $output = serveSshTo($key, EC::createKey('Ed25519'));

    expect(mb_substr_count($output, '[key rejected]'))->toBe(2)
        ->and($output)->toContain('Hello, stranger.');

    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(RequestException::class);
});

it('refuses to serve when the web app does not answer lookups', function (): void {
    Http::fake(['*' => Http::response(status: 403)]);

    $this->artisan('ssh:serve', ['--port' => 0])
        ->expectsOutputToContain('Could not look up key owners on '.route('ssh-key-owner.show'))
        ->assertFailed();
});

it('uses the configured host key', function (): void {
    lookUpKeyOwnersOnTheWebApp();
    $secretKey = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());

    config(['ssh.host_key' => base64_encode($secretKey)]);

    serveSshTo(EC::createKey('Ed25519'));

    expect(file_get_contents("{$this->keyPath}/ssh_host_ed25519_key"))->toBe($secretKey)
        ->and(fileperms("{$this->keyPath}/ssh_host_ed25519_key") & 0777)->toBe(0600)
        ->and(file_get_contents("{$this->keyPath}/ssh_host_ed25519_key.pub"))->toBe(sodium_crypto_sign_publickey_from_secretkey($secretKey));
});

it('refuses to serve with an invalid host key', function (string $hostKey): void {
    config(['ssh.host_key' => $hostKey]);

    $this->artisan('ssh:serve')
        ->expectsOutputToContain('SSH_HOST_KEY must be a base64 encoded Ed25519 secret key.')
        ->assertFailed();

    expect(File::exists($this->keyPath))->toBeFalse();
})->with([
    'not base64' => 'not a key!',
    'wrong length' => base64_encode(random_bytes(32)),
]);

it('refuses to serve without a lookup token', function (): void {
    config(['ssh.lookup_token' => null]);

    $this->artisan('ssh:serve')
        ->expectsOutputToContain('SSH_LOOKUP_TOKEN must be set, to the same value as on the web app.')
        ->assertFailed();
});
