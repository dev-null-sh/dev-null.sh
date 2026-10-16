<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\SshPublicKey;
use App\Models\SshKey;
use App\Models\User;
use App\Services\GitHub;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the public SSH keys (authentication and signing) of a user's GitHub account.
 */
final readonly class UpdateUserSshKeys
{
    private const int FreshForMinutes = 5;

    public function __construct(private GitHub $github)
    {
        //
    }

    public function handle(User $user, bool $unlessRecentlySynced = false): void
    {
        $cacheKey = "ssh-keys-synced:{$user->id}";

        if ($unlessRecentlySynced && Cache::has($cacheKey)) {
            return;
        }

        $username = $this->github->login($user->github_id);

        if ($username === null) {
            $user->sshKeys()->delete();

            Cache::put($cacheKey, true, now()->addMinutes(self::FreshForMinutes));

            return;
        }

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
        $keys = $this->github->request()->get($path, ['per_page' => 100])->throw()->json();

        return collect($keys)
            ->map(fn (array $key): ?SshPublicKey => SshPublicKey::tryFromOpenSsh($key['key']))
            ->filter()
            ->values();
    }
}
