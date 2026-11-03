<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class OrganizationPreviewController
{
    /**
     * What the hover card of an organization's commits adds to the profile they carry: how many commits it has published,
     * how many follow it and whether the viewer does.
     */
    public function show(#[CurrentUser] ?User $currentUser, Organization $organization): JsonResponse
    {
        return response()->json([
            'commitsCount' => Commit::query()->where('author_id', $organization->id)->approved()->count(),
            'followersCount' => $organization->followers()->newPivotQuery()->count(),
            'isFollowing' => $currentUser?->followedOrganizations()->newPivotQuery()->where('organization_id', $organization->id)->exists() ?? false,
        ]);
    }
}
