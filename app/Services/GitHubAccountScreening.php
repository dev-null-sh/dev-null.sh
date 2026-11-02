<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ban;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\User as GitHubUser;

final readonly class GitHubAccountScreening
{
    public function refusal(GitHubUser $githubUser): ?string
    {
        if (User::query()->where('github_id', (string) $githubUser->getId())->exists()) {
            return null;
        }

        // A banned account that deleted itself can't sign up again...
        if (Ban::query()->where('github_id', (string) $githubUser->getId())->exists()) {
            return __('Sign up blocked. This GitHub account is banned from /dev/null.');
        }

        $minimumAge = config('signup.min_account_age_days', 0);
        assert(is_int($minimumAge));

        if ($minimumAge > 0 && ! $this->isOldEnough($githubUser, $minimumAge)) {
            return __('Sign up blocked. GitHub accounts younger than :days days cannot join yet.', ['days' => $minimumAge]);
        }

        $requireEmail = config('signup.require_verified_email', false);
        assert(is_bool($requireEmail));

        // Socialite only sets the email when GitHub marks it primary and verified.
        if ($requireEmail && $githubUser->getEmail() === null) {
            return __('Sign up blocked. GitHub did not return a verified primary email. Check github.com/settings/emails, then try again.');
        }

        return null;
    }

    private function isOldEnough(GitHubUser $githubUser, int $minimumAge): bool
    {
        $createdAt = Arr::get($githubUser->getRaw(), 'created_at');

        if (! is_string($createdAt)) {
            return false;
        }

        return CarbonImmutable::parse($createdAt)->lte(now()->subDays($minimumAge));
    }
}
