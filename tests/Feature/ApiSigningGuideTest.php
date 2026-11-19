<?php

declare(strict_types=1);

use App\Dto\SshPublicKey;
use App\Models\Device;
use App\Models\User;
use App\Services\ApiExamples;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

use function SigningGuide\devnullHeaders;

/**
 * A home whose ~/.ssh/devnull is a fresh key, approved as a device of the user, as the Authentication guide has it.
 */
function signingGuideHome(User $user): string
{
    $home = sys_get_temp_dir().'/devnull-signing-'.Str::random(12);
    File::ensureDirectoryExists("{$home}/.ssh");
    Process::run(['ssh-keygen', '-t', 'ed25519', '-f', "{$home}/.ssh/devnull", '-N', '', '-q'])->throw();

    Device::factory()->for($user)->withPublicKey(SshPublicKey::fromOpenSsh(File::get("{$home}/.ssh/devnull.pub")))->create();

    return $home;
}

/**
 * The headers one of the guide's signing helpers makes, run as the guide shows it, with that home.
 *
 * @return array<string, string>
 */
function signingGuideHeaders(string $client, string $home, string $method, string $path, string $body): array
{
    $helpers = array_column(resolve(ApiExamples::class)->signing(), 'code', 'label');

    if ($client === 'Laravel') {
        if (! function_exists('SigningGuide\devnullHeaders')) {
            eval("namespace SigningGuide;\n".$helpers['Laravel']);
        }

        $previous = getenv('HOME');
        putenv("HOME={$home}");
        $headers = devnullHeaders($method, $path, $body);
        putenv("HOME={$previous}");

        return $headers;
    }

    if ($client === 'curl') {
        $script = $helpers['curl']."\ndevnull_sign \"\$1\" \"\$2\" \"\$3\"\nprintf '%s\\n%s\\n%s' \"\$ts\" \"\$nonce\" \"\$signature\"";
        [$timestamp, $nonce, $signature] = explode("\n", Process::env(['HOME' => $home])->run(['bash', '-c', $script, 'sign', $method, $path, $body])->throw()->output());

        return ['X-Devnull-Timestamp' => $timestamp, 'X-Devnull-Nonce' => $nonce, 'X-SSH-Signature' => $signature];
    }

    File::put("{$home}/devnull.mjs", $helpers['fetch']);
    $script = "import { devnullHeaders } from '{$home}/devnull.mjs'; const [method, path, body] = process.argv.slice(1); process.stdout.write(JSON.stringify(devnullHeaders(method, path, body)));";

    return json_decode(Process::env(['HOME' => $home])->run(['node', '--input-type=module', '-e', $script, $method, $path, $body])->throw()->output(), true, flags: JSON_THROW_ON_ERROR);
}

it("signs requests the API accepts with each client's helper from the guide", function (string $client): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $home = signingGuideHome($user);

    $requests = [
        ['GET', '/api/v1/me', ''],
        ['POST', "/api/v1/users/{$followed->handle}/follow", ''],
        ['POST', '/api/v1/commits/preview', (string) json_encode(['body' => 'Hello from the API!'])],
    ];

    foreach ($requests as [$method, $path, $body]) {
        $headers = signingGuideHeaders($client, $home, $method, $path, $body);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.Str::upper(Str::replace('-', '_', $name))] = $value;
        }

        $this->call($method, $path, server: $server, content: $body)->assertSuccessful();
    }

    File::deleteDirectory($home);
})->with(['curl', 'Laravel', 'fetch']);
