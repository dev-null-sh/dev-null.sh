<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $hasSponsorsListing = $this->hasSponsorsListing($githubUser->token, $username);

        return DB::transaction(function () use ($githubUser, $raw, $githubId, $username, $hasSponsorsListing): User {
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
                ...($hasSponsorsListing === null ? [] : ['has_sponsors_listing' => $hasSponsorsListing]),
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

    /**
     * The REST API doesn't say, so this asks GraphQL with the user's own token. Everything inside the
     * listing, even whether it's public, needs a read:user or read:org token, and sign-in only asks for
     * user:email. Null when GitHub can't answer, which keeps the last known value.
     */
    private function hasSponsorsListing(string $token, string $username): ?bool
    {
        try {
            $response = Http::withToken($token)
                ->connectTimeout(2)
                ->timeout(3)
                ->post('https://api.github.com/graphql', [
                    'query' => 'query { viewer { hasSponsorsListing } }',
                ]);
        } catch (ConnectionException $connectionException) {
            Log::warning('Could not check the GitHub Sponsors listing.', [
                'username' => $username,
                'error' => $connectionException->getMessage(),
            ]);

            return null;
        }

        if ($response->failed() || $response->json('errors') !== null) {
            Log::warning('Could not check the GitHub Sponsors listing.', [
                'username' => $username,
                'status' => $response->status(),
                'errors' => $response->json('errors') ?? $response->json('message'),
            ]);

            return null;
        }

        return $response->json('data.viewer.hasSponsorsListing') === true;
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
