<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as GitHubUser;

final readonly class SyncGitHubUser
{
    public function handle(GitHubUser $githubUser): User
    {
        /** @var array<string, mixed> $raw */
        $raw = $githubUser->getRaw();

        $username = (string) $githubUser->getNickname();

        $githubId = (string) $githubUser->getId();

        $attributes = [
            'username' => $username,
            'name' => $this->nullIfBlank($githubUser->getName()) ?? $username,
            'email' => $this->nullIfBlank($githubUser->getEmail()),
            'avatar_url' => $this->nullIfBlank($githubUser->getAvatar()),
            'bio' => $this->nullIfBlank(Arr::get($raw, 'bio')),
            'location' => $this->nullIfBlank(Arr::get($raw, 'location')),
            'website' => $this->website(Arr::get($raw, 'blog')),
            'company' => $this->nullIfBlank(Arr::get($raw, 'company')),
        ];

        return DB::transaction(function () use ($githubId, $username, $attributes): User {
            $previousOwner = User::query()
                ->where('username', $username)
                ->where('github_id', '!=', $githubId)
                ->lockForUpdate()
                ->first();

            if ($previousOwner !== null) {
                $previousOwner->update(['username' => 'renamed-'.$previousOwner->id]);
            }

            return User::query()->updateOrCreate(['github_id' => $githubId], $attributes);
        });
    }

    private function website(mixed $value): ?string
    {
        $website = $this->nullIfBlank($value);

        if ($website === null || Str::startsWith($website, ['http://', 'https://'])) {
            return $website;
        }

        return 'https://'.$website;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_trim($value);

        return $value === '' ? null : $value;
    }
}
