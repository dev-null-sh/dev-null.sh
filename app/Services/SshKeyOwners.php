<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\SshPublicKey;
use GuzzleHttp\Handler\CurlMultiHandler;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;

use function React\Promise\resolve;

/**
 * Looks up the user who owns an SSH key by asking the web app, without ever blocking the event loop.
 *
 * Requests go through a curl multi handle that the event loop ticks while they are in flight. Answers are kept in two
 * small LRU caches: owners for a minute, so spikes of logins don't all reach the web app, and unknown keys for a few
 * seconds, so a key synced from GitHub is recognized right away and junk keys can't push real users out.
 */
final class SshKeyOwners
{
    private const float TickSeconds = 0.005;

    /**
     * Keeps resolved addresses for longer than curl's default minute, in case its resolver blocks.
     */
    private const int DnsCacheSeconds = 600;

    private const string CheckFingerprint = 'SHA256:ssh-serve-check';

    /**
     * @var array<string, array{username: string, expiresAt: float}>
     */
    private array $owners = [];

    /**
     * @var array<string, float>
     */
    private array $unknown = [];

    /**
     * @var array<string, PromiseInterface<?string>>
     */
    private array $pending = [];

    private readonly CurlMultiHandler $handler;

    private ?TimerInterface $ticker = null;

    public function __construct(
        private readonly string $url,
        private readonly string $token,
        private readonly int $cacheSize = 10_000,
        private readonly float $ownerSeconds = 60,
        private readonly float $unknownSeconds = 5,
    ) {
        $this->handler = new CurlMultiHandler(['select_timeout' => 0]);
    }

    /**
     * Checks that the web app answers lookups, with the right token.
     *
     * @return PromiseInterface<null>
     */
    public function check(): PromiseInterface
    {
        return $this->lookUp(self::CheckFingerprint)->then(fn (): null => null);
    }

    /**
     * @return PromiseInterface<?string>
     */
    public function find(SshPublicKey $publicKey): PromiseInterface
    {
        $fingerprint = $publicKey->fingerprint();
        $now = microtime(true);

        $owner = $this->owners[$fingerprint] ?? null;

        if ($owner !== null && $owner['expiresAt'] > $now) {
            unset($this->owners[$fingerprint]);
            $this->owners[$fingerprint] = $owner;

            return resolve($owner['username']);
        }

        if (($this->unknown[$fingerprint] ?? 0) > $now) {
            return resolve(null);
        }

        return $this->lookUp($fingerprint);
    }

    /**
     * @return PromiseInterface<?string>
     */
    private function lookUp(string $fingerprint): PromiseInterface
    {
        return $this->pending[$fingerprint] ??= $this->request($fingerprint);
    }

    /**
     * @return PromiseInterface<?string>
     */
    private function request(string $fingerprint): PromiseInterface
    {
        /** @var Deferred<?string> $deferred */
        $deferred = new Deferred;

        Http::setHandler($this->handler)
            ->async()
            ->withToken($this->token)
            ->acceptJson()
            ->timeout(5)
            ->withOptions(['curl' => [CURLOPT_DNS_CACHE_TIMEOUT => self::DnsCacheSeconds]])
            ->get($this->url, ['fingerprint' => $fingerprint])
            ->buildPromise()
            ->then(function (Response|Throwable $response) use ($deferred, $fingerprint): void {
                unset($this->pending[$fingerprint]);

                if ($response instanceof Throwable) {
                    $deferred->reject($response);

                    return;
                }

                if ($response->failed()) {
                    $deferred->reject(new RequestException($response));

                    return;
                }

                $username = $response->json('username');
                $username = is_string($username) ? $username : null;

                $this->remember($fingerprint, $username);

                $deferred->resolve($username);
            }, function (Throwable $exception) use ($deferred, $fingerprint): void {
                unset($this->pending[$fingerprint]);

                $deferred->reject($exception);
            });

        $this->ticker ??= Loop::addPeriodicTimer(self::TickSeconds, $this->tick(...));

        return $deferred->promise();
    }

    /**
     * Lets curl make progress on the requests in flight, then stops ticking once there are none left.
     */
    private function tick(): void
    {
        $this->handler->tick();

        if ($this->pending === [] && $this->ticker instanceof TimerInterface) {
            Loop::cancelTimer($this->ticker);

            $this->ticker = null;
        }
    }

    private function remember(string $fingerprint, ?string $username): void
    {
        $now = microtime(true);

        if ($username === null) {
            unset($this->unknown[$fingerprint]);
            $this->unknown[$fingerprint] = $now + $this->unknownSeconds;

            if (count($this->unknown) > $this->cacheSize) {
                unset($this->unknown[array_key_first($this->unknown)]);
            }

            return;
        }

        unset($this->unknown[$fingerprint], $this->owners[$fingerprint]);
        $this->owners[$fingerprint] = ['username' => $username, 'expiresAt' => $now + $this->ownerSeconds];

        if (count($this->owners) > $this->cacheSize) {
            unset($this->owners[array_key_first($this->owners)]);
        }
    }
}
