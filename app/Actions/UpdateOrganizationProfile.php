<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Services\GitHubProvider;
use App\Models\Organization;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\HttpClientException;

/**
 * Refreshes an organization's public GitHub profile without changing its members or chosen color.
 */
final readonly class UpdateOrganizationProfile
{
    public function __construct(
        private GitHubProvider $github,
        private UpdateOrCreateOrganization $organizations,
    ) {
        //
    }

    public function handle(Organization $organization): bool
    {
        try {
            $githubId = $organization->profile->github_id;
            $account = $this->github->account($githubId);

            // Never follow a renamed handle to a different account, or turn a user into an organization.
            if ($account === null
                || ($account['type'] ?? null) !== 'Organization'
                || ! is_int($account['id'] ?? null)
                || (string) $account['id'] !== $githubId
                || ! is_string($account['login'] ?? null)
                || $account['login'] === '') {
                return false;
            }

            $updated = $this->organizations->handle($account);

            return $updated->is($organization);
        } catch (HttpClientException|UniqueConstraintViolationException $exception) {
            report($exception);

            return false;
        }
    }
}
