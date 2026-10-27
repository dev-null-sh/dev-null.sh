<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\GitHubProfile;
use App\Enums\ProfileType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\User as GitHubUser;

final readonly class UpdateOrCreateUser
{
    public function __construct(private UpdateStaleHandles $updateStaleHandles)
    {
        //
    }

    public function handle(GitHubUser $githubUser): User
    {
        $githubId = (string) $githubUser->getId();
        $profile = GitHubProfile::fromSocialite($githubUser);
        $email = mb_trim((string) $githubUser->getEmail());

        $hasSponsorsListing = $this->hasSponsorsListing($githubUser->token, $profile->handle);

        return DB::transaction(function () use ($githubId, $profile, $email, $hasSponsorsListing): User {
            $this->updateStaleHandles->handle($profile->handle, $githubId);

            Profile::query()->updateOrCreate([
                'github_id' => $githubId,
            ], [
                'type' => ProfileType::User,
                ...$profile->attributes(),
                'github_synced_at' => now(),
            ]);

            return User::query()->updateOrCreate([
                'github_id' => $githubId,
            ], [
                'company' => $profile->company,
                'email' => $email === '' ? null : $email,
                ...($hasSponsorsListing === null ? [] : ['has_sponsors_listing' => $hasSponsorsListing]),
            ]);
        });
    }

    /**
     * The REST API doesn't say, so this asks GraphQL with the user's own token. Everything inside the
     * listing, even whether it's public, needs a read:user or read:org token, and sign-in only asks for
     * user:email. Null when GitHub can't answer, which keeps the last known value.
     */
    private function hasSponsorsListing(string $token, string $handle): ?bool
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
                'handle' => $handle,
                'error' => $connectionException->getMessage(),
            ]);

            return null;
        }

        if ($response->failed() || $response->json('errors') !== null) {
            Log::warning('Could not check the GitHub Sponsors listing.', [
                'handle' => $handle,
                'status' => $response->status(),
                'errors' => $response->json('errors') ?? $response->json('message'),
            ]);

            return null;
        }

        return $response->json('data.viewer.hasSponsorsListing') === true;
    }
}
