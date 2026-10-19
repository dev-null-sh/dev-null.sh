<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpsertBranches;
use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Conversation;
use App\Models\User;
use App\Queries\BranchCommitsQuery;
use App\Queries\BranchStatsQuery;
use App\Services\BranchDnsVerification;
use App\Services\Branches;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class BranchController
{
    public function show(
        Request $request,
        string $branch,
        #[CurrentUser] ?User $user,
        Branches $branches,
        UpsertBranches $upsertBranches,
        BranchCommitsQuery $commits,
        BranchStatsQuery $stats,
        BranchDnsVerification $verification,
    ): Response|RedirectResponse {
        $normalized = mb_strtolower($branch);

        if (! $branches->isValid($normalized)) {
            throw new NotFoundHttpException;
        }

        if ($branch !== $normalized) {
            return redirect()->route('branch.show', [
                'branch' => $normalized,
                ...$request->only('sort'),
            ], 301);
        }

        $upsertBranches->handle([$normalized]);
        $branchModel = Branch::query()->where('name', $normalized)->with(['members.user'])->firstOrFail();
        $trending = $request->string('sort')->toString() === 'trending';
        $role = $user instanceof User ? $branchModel->roleOf($user) : null;

        return Inertia::render('branch/show', [
            'branch' => $this->payload($branchModel, $verification, $user, $role),
            'sort' => $trending ? 'trending' : 'latest',
            'isRanked' => $trending,
            'commits' => Inertia::scroll(fn () => $commits->builder($normalized, $user, $trending)->cursorPaginate(20)),
            'stats' => $stats->get($normalized),
        ]);
    }

    public function edit(
        Branch $branch,
        #[CurrentUser] User $user,
        BranchDnsVerification $verification,
    ): Response {
        abort_unless($branch->roleOf($user) !== null, 403);

        $branch->load(['members.user']);

        return Inertia::render('branch/settings', [
            'branch' => $this->payload($branch, $verification, $user, $branch->roleOf($user)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Branch $branch, BranchDnsVerification $verification, ?User $user, ?BranchRole $role): array
    {
        return [
            'name' => $branch->name,
            'description' => $branch->description,
            'domain_description' => $branch->domain_description,
            'brand_color' => $branch->isVerified() ? $branch->brand_color : null,
            'og_image_url' => $branch->isVerified() ? $branch->og_image_url : null,
            'domain' => $branch->domain,
            'verified_domain' => $branch->verified_domain,
            'is_verified' => $branch->isVerified(),
            'verification_pending' => $branch->hasPendingVerification(),
            'is_owned' => $branch->isOwned(),
            'claim_pending' => $branch->hasPendingClaim(),
            'claim_requested_by_viewer' => $user instanceof User
                && $branch->hasPendingClaim()
                && $branch->claim_requested_by === $user->id,
            'can_claim' => $user instanceof User
                && ! $branch->isOwned()
                && ! $branch->hasPendingClaim()
                && Conversation::userMayVerifyBranch($user, $branch->name),
            'can_verify' => $user instanceof User && Conversation::userMayVerifyBranch($user, $branch->name),
            'org_locked' => Conversation::matchingBranch($branch->name)->exists(),
            'verification_host' => $branch->domain !== null ? $verification->hostFor($branch->domain) : null,
            'verification_record' => $branch->domain !== null && $branch->verification_token !== null
                ? $verification->recordValue($branch)
                : null,
            'viewer_role' => $role?->value,
            'is_watching' => $user instanceof User && $branch->watches()->where('user_id', $user->id)->exists(),
            'members' => $branch->relationLoaded('members')
                ? $branch->members
                    ->sortBy(fn ($member): array => [$member->role === BranchRole::Maintainer ? 0 : 1, $member->user->username])
                    ->values()
                    ->map(fn ($member): array => [
                        'role' => $member->role->value,
                        'user' => [
                            ...$member->user->only(['id', 'username', 'name', 'avatar_url']),
                            'lane' => $member->user->lane(),
                        ],
                    ])
                    ->all()
                : [],
        ];
    }
}
