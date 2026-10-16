<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Dto\SshPublicKey;
use App\Services\SshKeyOwners;
use App\Services\TerminalSession;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;
use WilliamEggers\React\SSH\Channel;
use WilliamEggers\React\SSH\Connection;
use WilliamEggers\React\SSH\Enums\DisconnectReason;
use WilliamEggers\React\SSH\Server;
use WilliamEggers\React\SSH\Values\KeyboardInteractiveConfig;
use WilliamEggers\React\SSH\Values\WinSize;

use function React\Promise\resolve;

#[Signature('ssh:serve {--host= : The interface to listen on} {--port= : The port to listen on}')]
#[Description('Serve the devnull terminal app over SSH')]
final class ServeSshCommand extends Command
{
    /**
     * Like OpenSSH's MaxAuthTries: a client offering more keys is disconnected.
     */
    private const int MaxKeysPerConnection = 6;

    private const int ReportEverySeconds = 60;

    private SshKeyOwners $keyOwners;

    /**
     * @var array<class-string<Throwable>, float>
     */
    private array $reportedAt = [];

    public function handle(): int
    {
        $keyPath = config()->string('ssh.key_path');

        if (! $this->storeHostKey($keyPath)) {
            $this->components->error('SSH_HOST_KEY must be a base64 encoded Ed25519 secret key.');

            return self::FAILURE;
        }

        $token = config('ssh.lookup_token');

        if (! is_string($token) || $token === '') {
            $this->components->error('SSH_LOOKUP_TOKEN must be set, to the same value as on the web app.');

            return self::FAILURE;
        }

        $this->keyOwners = new SshKeyOwners($url = route('ssh-key-owner.show'), $token);

        $failure = $this->checkKeyOwners();

        if ($failure instanceof Throwable) {
            $this->components->error("Could not look up key owners on {$url}: {$failure->getMessage()}");

            return self::FAILURE;
        }

        $host = $this->option('host');
        $host = is_string($host) ? $host : config()->string('ssh.host');

        $port = $this->option('port');
        $port = is_numeric($port) ? (int) $port : config()->integer('ssh.port');

        $server = new Server("{$host}:{$port}", config()->integer('ssh.idle_timeout'), hostKeyPath: $keyPath);

        $server->enableAuthentication();

        $server->on('connection', function (Connection $connection): void {
            $this->accept($connection);
        });

        $this->components->info("Serving SSH on {$host}:{$port}.");

        Loop::run();

        return self::SUCCESS;
    }

    /**
     * Writes the configured Ed25519 host key where the server loads its host keys from, or returns false when it is invalid.
     */
    private function storeHostKey(string $keyPath): bool
    {
        $hostKey = config('ssh.host_key');

        if (! is_string($hostKey) || $hostKey === '') {
            return true;
        }

        $secretKey = base64_decode($hostKey, true);

        if ($secretKey === false || $secretKey === '' || mb_strlen($secretKey, '8bit') !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            return false;
        }

        File::ensureDirectoryExists($keyPath, 0700);

        $umask = umask(0077);

        try {
            File::put("{$keyPath}/ssh_host_ed25519_key", $secretKey);
            File::put("{$keyPath}/ssh_host_ed25519_key.pub", sodium_crypto_sign_publickey_from_secretkey($secretKey));
        } finally {
            umask($umask);
        }

        return true;
    }

    /**
     * Runs the event loop until the web app has answered a lookup, and returns why it couldn't.
     */
    private function checkKeyOwners(): ?Throwable
    {
        $failure = null;

        $this->keyOwners->check()->then(function (): void {
            Loop::stop();
        }, function (Throwable $exception) use (&$failure): void {
            $failure = $exception;

            Loop::stop();
        });

        Loop::run();

        return $failure;
    }

    /**
     * Keys mirrored from GitHub log in as their owner. Other keys are rejected before the client signs with them, so it
     * offers its next one, and a client without a known key falls back to a keyboard-interactive round without prompts,
     * connecting as a stranger.
     */
    private function accept(Connection $connection): void
    {
        $username = null;
        $offeredKeys = [];

        $connection->setKeyboardInteractiveConfig(new KeyboardInteractiveConfig);

        $offer = function (string $publicKey) use (&$offeredKeys, $connection): bool {
            $offeredKeys[$publicKey] = true;

            if (count($offeredKeys) > self::MaxKeysPerConnection) {
                $connection->disconnect('Too many authentication failures', DisconnectReason::NO_MORE_AUTH_METHODS_AVAILABLE);

                return false;
            }

            return true;
        };

        $connection->on('publickey-query', function (string $name, string $publicKey, Deferred $acceptable) use ($offer): void {
            if (! $offer($publicKey)) {
                $acceptable->resolve(false);

                return;
            }

            $this->ownerOf($publicKey)->then(function (?string $owner) use ($acceptable): void {
                $acceptable->resolve($owner !== null);
            });
        });

        $connection->on('authenticate', function (string $name, string $method, array $credentials, Deferred $authenticated) use (&$username, $offer): void {
            $publicKey = $credentials[0] ?? null;

            if ($method !== 'publickey' || ! is_string($publicKey) || ! $offer($publicKey)) {
                $authenticated->resolve($method === 'keyboard-interactive');

                return;
            }

            $this->ownerOf($publicKey)->then(function (?string $owner) use (&$username, $authenticated): void {
                $username ??= $owner;

                $authenticated->resolve($owner !== null);
            });
        });

        $connection->on('channel.open', function (Channel $channel) use (&$username): void {
            $channel->on('shell-request', function (Deferred $started) use ($channel, &$username): void {
                $started->resolve(true);

                $this->startSession($channel, $username);
            });

            $channel->on('exec-request', function (string $command, Deferred $started) use ($channel): void {
                $started->resolve(true);

                $channel->exit(1, "devnull.sh is interactive only, connect without a command.\r\n");
            });
        });
    }

    /**
     * The username that owns a public key ("<type> <base64>"), or null when nobody does or it can't be looked up.
     *
     * @return PromiseInterface<?string>
     */
    private function ownerOf(string $publicKey): PromiseInterface
    {
        $publicKey = SshPublicKey::tryFromOpenSsh($publicKey);

        if (! $publicKey instanceof SshPublicKey) {
            return resolve(null);
        }

        return $this->keyOwners->find($publicKey)->then(null, function (Throwable $exception): null {
            $this->reportLookupFailure($exception);

            return null;
        });
    }

    /**
     * Reports why lookups fail, at most once a minute per kind of failure, so an outage doesn't flood the error tracker.
     */
    private function reportLookupFailure(Throwable $exception): void
    {
        $now = microtime(true);

        if (($this->reportedAt[$exception::class] ?? 0) > $now - self::ReportEverySeconds) {
            return;
        }

        $this->reportedAt[$exception::class] = $now;

        report($exception);
    }

    private function startSession(Channel $channel, ?string $username): void
    {
        $terminal = $channel->getTerminalInfo();

        $session = new TerminalSession(
            $username,
            $terminal->widthChars ?? 0,
            $terminal->heightRows ?? 0,
            function (string $data) use ($channel): void {
                $channel->write($data);
            },
            function () use ($channel): void {
                $channel->exit(0);
            },
        );

        $channel->on('window-change', function (WinSize $size) use ($session): void {
            $session->resize($size->cols, $size->rows);
        });

        $channel->on('data', function (string $data) use ($session): void {
            $session->input($data);
        });

        $session->start();
    }
}
