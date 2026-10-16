<?php

declare(strict_types=1);

use App\Dto\SshPublicKey;
use App\Services\SshKeyOwners;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use React\EventLoop\Loop;
use React\EventLoop\StreamSelectLoop;
use React\Promise\PromiseInterface;
use Tests\Support\SshKeyPair;

beforeEach(function (): void {
    Loop::set(new StreamSelectLoop);
});

/**
 * Runs the event loop until the promise settles, and returns its value or reason.
 */
function settle(PromiseInterface $promise): mixed
{
    $settled = false;
    $result = null;

    $promise->then(function (mixed $value) use (&$settled, &$result): void {
        [$settled, $result] = [true, $value];
        Loop::stop();
    }, function (Throwable $reason) use (&$settled, &$result): void {
        [$settled, $result] = [true, $reason];
        Loop::stop();
    });

    if (! $settled) {
        $timeout = Loop::addTimer(5, Loop::stop(...));
        Loop::run();
        Loop::cancelTimer($timeout);
    }

    return $result;
}

function sshKeyOwners(int $cacheSize = 10, float $ownerSeconds = 60, float $unknownSeconds = 60): SshKeyOwners
{
    return new SshKeyOwners('https://devnull.test/api/ssh/key-owner', 'secret-token', $cacheSize, $ownerSeconds, $unknownSeconds);
}

/**
 * @param  array<string, ?string>  $owners
 */
function fakeKeyOwners(array $owners = []): void
{
    Http::fake(fn (Request $request) => Http::response(['username' => $owners[$request['fingerprint']] ?? null]));
}

function publicKey(): SshPublicKey
{
    return SshKeyPair::generate()->publicKey;
}

it('asks the web app who owns a key', function (): void {
    $publicKey = publicKey();
    fakeKeyOwners([$publicKey->fingerprint() => 'taylor']);

    expect(settle(sshKeyOwners()->find($publicKey)))->toBe('taylor');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://devnull.test/api/ssh/key-owner?fingerprint='.urlencode($publicKey->fingerprint())
        && $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('answers null for a key nobody owns', function (): void {
    fakeKeyOwners();

    expect(settle(sshKeyOwners()->find(publicKey())))->toBeNull();
});

it('caches answers, including unknown keys', function (): void {
    [$known, $unknown] = [publicKey(), publicKey()];
    fakeKeyOwners([$known->fingerprint() => 'taylor']);
    $owners = sshKeyOwners();

    settle($owners->find($known));
    settle($owners->find($unknown));

    expect(settle($owners->find($known)))->toBe('taylor')
        ->and(settle($owners->find($unknown)))->toBeNull();

    Http::assertSentCount(2);
});

it('asks once for concurrent lookups of the same key', function (): void {
    $publicKey = publicKey();
    fakeKeyOwners([$publicKey->fingerprint() => 'taylor']);
    $owners = sshKeyOwners();

    $first = $owners->find($publicKey);
    $second = $owners->find($publicKey);

    expect(settle($first))->toBe('taylor')
        ->and(settle($second))->toBe('taylor');

    Http::assertSentCount(1);
});

it('asks again once an answer expires', function (string $username, float $ownerSeconds, float $unknownSeconds): void {
    $publicKey = publicKey();
    fakeKeyOwners([$publicKey->fingerprint() => $username === '' ? null : $username]);
    $owners = sshKeyOwners(ownerSeconds: $ownerSeconds, unknownSeconds: $unknownSeconds);

    settle($owners->find($publicKey));
    settle($owners->find($publicKey));

    Http::assertSentCount(2);
})->with([
    'owner' => ['taylor', 0, 60],
    'unknown key' => ['', 60, 0],
]);

it('evicts the least recently used owner', function (): void {
    [$a, $b, $c] = [publicKey(), publicKey(), publicKey()];
    fakeKeyOwners([$a->fingerprint() => 'a', $b->fingerprint() => 'b', $c->fingerprint() => 'c']);
    $owners = sshKeyOwners(cacheSize: 2);

    settle($owners->find($a));
    settle($owners->find($b));
    settle($owners->find($a));
    settle($owners->find($c));
    Http::assertSentCount(3);

    settle($owners->find($a));
    Http::assertSentCount(3);

    settle($owners->find($b));
    Http::assertSentCount(4);
});

it('never evicts owners to make room for unknown keys', function (): void {
    $known = publicKey();
    fakeKeyOwners([$known->fingerprint() => 'taylor']);
    $owners = sshKeyOwners(cacheSize: 1);

    settle($owners->find($known));
    settle($owners->find(publicKey()));
    settle($owners->find(publicKey()));

    expect(settle($owners->find($known)))->toBe('taylor');

    Http::assertSentCount(3);
});

it('checks that the web app answers lookups', function (): void {
    fakeKeyOwners();

    expect(settle(sshKeyOwners()->check()))->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request['fingerprint'] === 'SHA256:ssh-serve-check');
});

it('fails when the web app answers with an error', function (): void {
    Http::fake(['*' => Http::response(status: 500)]);

    expect(settle(sshKeyOwners()->find(publicKey())))->toBeInstanceOf(RequestException::class);
});

it('fails when the web app cannot be reached', function (): void {
    Http::fake(['*' => Http::failedConnection()]);

    expect(settle(sshKeyOwners()->find(publicKey())))->toBeInstanceOf(ConnectionException::class);
});

it('fails, and asks again next time, when the request itself fails', function (): void {
    Http::fake(['other.test/*' => Http::response()]);
    $owners = sshKeyOwners();
    $publicKey = publicKey();

    expect(settle($owners->find($publicKey)))->toBeInstanceOf(StrayRequestException::class)
        ->and(settle($owners->find($publicKey)))->toBeInstanceOf(StrayRequestException::class);
});

it('stops ticking once no lookups are in flight', function (): void {
    fakeKeyOwners();
    $owners = sshKeyOwners();

    settle($owners->find(publicKey()));

    $idle = false;
    Loop::addTimer(0.05, function () use (&$idle): void {
        $idle = true;
        Loop::stop();
    });
    $safety = Loop::addTimer(1, Loop::stop(...));
    Loop::run();
    Loop::cancelTimer($safety);

    expect($idle)->toBeTrue()
        ->and(new ReflectionProperty($owners, 'ticker')->getValue($owners))->toBeNull();
});
