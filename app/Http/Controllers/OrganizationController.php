<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Requests\ShowOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Queries\OrganizationMentionsQuery;
use App\Services\PageMetadata;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationController
{
    /**
     * Organization pages are public, at the "@handle" of users, and show the organization's GitHub profile and the
     * commits that mention it, in the lane its admins chose or /dev/null's blue, with its admins, first one first, how
     * many follow it and whether the viewer does, and the organization's link preview, and its members the way to its
     * settings. Like users' pages, they move for good to the handle's own case.
     */
    public function show(ShowOrganizationRequest $request, #[CurrentUser] ?User $currentUser, OrganizationMentionsQuery $mentions, PageMetadata $metadata): RedirectResponse|Response
    {
        $organization = $request->organization();
        assert($organization instanceof Organization);

        if ($request->route('user') !== $organization->handle) {
            // The raw query, since Symfony's normalized one keeps only the last value of a repeated key...
            $query = $request->server->getString('QUERY_STRING');

            return redirect(route('user.show', $organization->handle).($query === '' ? '' : "?{$query}"), 301, ['Cache-Control' => 'max-age=86400, private']);
        }

        return Inertia::render('organization/show', [
            // The Mentions tab tells a restored commit belongs to it by the ID, whatever handle the commit mentions.
            'organization' => $organization->makeVisible('id'),
            'lane' => $organization->lane(),
            'tab' => $request->tab(...),
            'commits' => Inertia::scroll(fn () => $mentions->builder($organization, $currentUser)
                ->cursorPaginate(20)
                ->appends(['tab' => $request->tab()])),
            'claimUrl' => route('organization-admin.store', $organization),
            // Admins land on the Profile tab, which only they have...
            'settingsUrl' => match ($currentUser instanceof User ? $organization->roleOf($currentUser) : null) {
                OrganizationRole::Admin => route('organization-profile.edit', $organization),
                OrganizationRole::Member => route('organization-member.index', $organization),
                null => null,
            },
            'admins' => fn (): Collection => $organization->admins()->oldest('organization_members.created_at')->get()->makeHidden('pivot'),
            'followersCount' => fn (): int => $organization->followers()->newPivotQuery()->count(),
            'isFollowing' => fn (): bool => $currentUser?->followedOrganizations()->newPivotQuery()->where('organization_id', $organization->id)->exists() ?? false,
        ])->withViewData(['preview' => $currentUser instanceof User || $request->inertia() ? null : $metadata->organization($organization)]);
    }
}
