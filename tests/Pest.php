<?php

declare(strict_types=1);

use App\Actions\CreateCommitSeries;
use App\Dto\SshPublicKey;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * @param  list<User>  $following
 * @param  list<User>  $followers
 */
function fakeGitHubFollows(User $user, array $following = [], array $followers = []): void
{
    $toResponse = fn (array $users): array => array_map(
        fn (User $user): array => ['id' => (int) $user->github_id, 'login' => $user->username],
        $users,
    );

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => $user->username]),
        "api.github.com/users/{$user->username}/following*" => Http::response($toResponse($following)),
        "api.github.com/users/{$user->username}/followers*" => Http::response($toResponse($followers)),
    ]);
}

function fakeGitHubSponsorsListing(?bool $isPublic = null): void
{
    Http::fake([
        'api.github.com/graphql' => Http::response(['data' => ['viewer' => [
            'sponsorsListing' => $isPublic === null ? null : ['isPublic' => $isPublic],
        ]]]),
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
 * @return Collection<int, Commit>
 */
function commitSeries(?User $user = null, int $size = 3, CommitStatus $status = CommitStatus::Approved): Collection
{
    $parts = resolve(CreateCommitSeries::class)->handle(
        $user ?? User::factory()->create(),
        array_map(fn (int $position): string => "Part {$position} of the series", range(1, $size)),
    );

    Commit::query()->whereKey($parts->modelKeys())->update(['status' => $status]);

    return $parts->fresh();
}
