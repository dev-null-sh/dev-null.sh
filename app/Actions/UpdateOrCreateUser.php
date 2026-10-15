<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as GitHubUser;

final readonly class UpdateOrCreateUser
{
    public function handle(GitHubUser $githubUser): User
    {
        /** @var array<string, mixed> $raw */
        $raw = $githubUser->getRaw();

        $githubId = (string) $githubUser->getId();
        $username = (string) $githubUser->getNickname();

        return DB::transaction(function () use ($githubUser, $raw, $githubId, $username): User {
            $this->freeUsername($username, $githubId);

            return User::query()->updateOrCreate([
                'github_id' => $githubId,
            ], [
                'username' => $username,
                'name' => $this->nullIfBlank($githubUser->getName()) ?? $username,
                'email' => $this->nullIfBlank($githubUser->getEmail()),
                'avatar_url' => $this->nullIfBlank($githubUser->getAvatar()),
                'bio' => $this->nullIfBlank(Arr::get($raw, 'bio')),
                'location' => $this->nullIfBlank(Arr::get($raw, 'location')),
                'website' => $this->website(Arr::get($raw, 'blog')),
                'company' => $this->nullIfBlank(Arr::get($raw, 'company')),
            ]);
        });
    }

    /**
     * GitHub usernames are unique case-insensitively, so another user holding this one renamed away on GitHub. The
     * placeholder starts with an underscore, which no GitHub username can, and their next sign-in restores a real one.
     */
    private function freeUsername(string $username, string $githubId): void
    {
        User::query()
            ->whereRaw('LOWER(username) = ?', [mb_strtolower($username)])
            ->where('github_id', '!=', $githubId)
            ->each(function (User $user): void {
                $user->update(['username' => '_renamed-'.$user->github_id]);
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
