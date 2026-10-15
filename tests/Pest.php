<?php

declare(strict_types=1);

use App\Dto\SshPublicKey;
use App\Jobs\UnfurlLink;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\Support\SshKeyPair;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Feature', 'Unit');

expect()->extend('toBeOne', fn () => $this->toBe(1));

function something(): void
{
    // ..
}

function sshFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/ssh/'.$name);
}

/**
 * @param  list<SshPublicKey>  $keys
 * @param  list<SshPublicKey>  $signingKeys
 */
function fakeGitHubSshKeys(User $user, array $keys = [], array $signingKeys = []): void
{
    $toResponse = fn (array $publicKeys): array => array_map(
        fn (SshPublicKey $publicKey): array => ['id' => 1, 'key' => $publicKey->toOpenSsh()],
        $publicKeys,
    );

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $user->username]),
        "api.github.com/users/{$user->username}/keys*" => Http::response($toResponse($keys)),
        "api.github.com/users/{$user->username}/ssh_signing_keys*" => Http::response($toResponse($signingKeys)),
    ]);
}

/**
 * @return array{payload: string, signature: string}
 */
function signedCommit(SshKeyPair $keyPair, string $body, ?int $timestamp = null, ?string $nonce = null, string $replyTo = ''): array
{
    $payload = implode("\n", [
        $timestamp ?? now()->getTimestamp(),
        $nonce ?? bin2hex(random_bytes(16)),
        $replyTo,
        $body,
    ]);

    return ['payload' => $payload, 'signature' => $keyPair->sign($payload)];
}

function fakeAvatar(): string
{
    $image = imagecreatetruecolor(4, 4);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 0, 0));

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/**
 * @param  array<string, mixed>|null  $payload
 */
function failedJob(string $queue = 'default', ?CarbonInterface $failedAt = null, string $exception = "RuntimeException: Something broke\nStack trace:\n#0 {main}", ?array $payload = null): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => $queue,
        'payload' => json_encode($payload ?? ['uuid' => $uuid, 'displayName' => UnfurlLink::class, 'attempts' => 1]),
        'exception' => $exception,
        'failed_at' => $failedAt ?? now(),
    ]);

    return $uuid;
}

function queuedJob(string $queue, CarbonInterface $createdAt): void
{
    DB::table('jobs')->insert([
        'queue' => $queue,
        'payload' => json_encode(['uuid' => (string) Str::uuid(), 'displayName' => UnfurlLink::class]),
        'attempts' => 0,
        'available_at' => $createdAt->getTimestamp(),
        'created_at' => $createdAt->getTimestamp(),
    ]);
}
