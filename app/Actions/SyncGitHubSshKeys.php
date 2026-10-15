<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\SshPublicKey;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Mirrors the public SSH keys (authentication and signing) of a user's GitHub account.
 */
final readonly class SyncGitHubSshKeys
{
    private const int FreshForMinutes = 5;

    public function handle(User $user, bool $unlessRecentlySynced = false): void
    {
        $cacheKey = "ssh-keys-synced:{$user->id}";

        if ($unlessRecentlySynced && Cache::has($cacheKey)) {
            return;
        }

        // Look the account up by its immutable ID: a username can be renamed and then claimed by someone else.
        $account = $this->github()->get("user/{$user->github_id}");

        if ($account->notFound()) {
            $user->sshKeys()->delete();

            Cache::put($cacheKey, true, now()->addMinutes(self::FreshForMinutes));

            return;
        }

        $username = $account->throw()->json('login');
        assert(is_string($username));

        $fingerprints = $this->publicKeys("users/{$username}/keys")
            ->merge($this->publicKeys("users/{$username}/ssh_signing_keys"))
            ->map(fn (SshPublicKey $publicKey): string => $publicKey->fingerprint())
            ->unique()
            ->values();

        DB::transaction(function () use ($user, $fingerprints): void {
            $user->sshKeys()->whereNotIn('fingerprint', $fingerprints)->delete();

            // A single atomic statement: concurrent syncs can't collide on the unique fingerprint.
            SshKey::query()->upsert(
                $fingerprints->map(fn (string $fingerprint): array => [
                    'user_id' => $user->id,
                    'fingerprint' => $fingerprint,
                ])->all(),
                uniqueBy: ['fingerprint'],
                update: ['user_id'],
            );
        });

        Cache::put($cacheKey, true, now()->addMinutes(self::FreshForMinutes));
    }

    /**
     * @return Collection<int, SshPublicKey>
     */
    private function publicKeys(string $path): Collection
    {
        /** @var list<array{key: string}> $keys */
        $keys = $this->github()->get($path, ['per_page' => 100])->throw()->json();

        return collect($keys)
            ->map(fn (array $key): ?SshPublicKey => SshPublicKey::tryFromOpenSsh($key['key']))
            ->filter()
            ->values();
    }

    private function github(): PendingRequest
    {
        $request = Http::baseUrl('https://api.github.com')
            ->accept('application/vnd.github+json')
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->connectTimeout(3)
            ->timeout(5);

        $clientId = config('services.github.client_id');
        $clientSecret = config('services.github.client_secret');

        // Authenticating as the OAuth app raises the rate limit from 60 to 5,000 requests per hour.
        if (is_string($clientId) && is_string($clientSecret)) {
            $request->withBasicAuth($clientId, $clientSecret);
        }

        return $request;
    }
}
