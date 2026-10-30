<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Dto\SshPublicKey;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the public SSH keys (authentication and signing) of a user's GitHub account.
 */
final readonly class UpdateUserSshKeys
{
    private const int FreshForMinutes = 5;

    public function __construct(private GitHubProvider $github)
    {
        //
    }

    public function handle(User $user, bool $unlessRecentlySynced = false): void
    {
        $cacheKey = "ssh-keys-synced:{$user->id}";

        if ($unlessRecentlySynced && Cache::has($cacheKey)) {
            return;
        }

        $handle = $this->github->login($user->github_id);

        if ($handle === null) {
            $user->sshKeys()->delete();

            Cache::put($cacheKey, true, now()->addMinutes(self::FreshForMinutes));

            return;
        }

        $fingerprints = collect($this->github->sshKeys($handle))
            ->map(fn (string $key): ?SshPublicKey => SshPublicKey::tryFromOpenSsh($key))
            ->filter()
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
}
