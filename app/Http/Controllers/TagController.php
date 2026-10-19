<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpsertTags;
use App\Enums\TagRole;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\User;
use App\Queries\HashtagCommitsQuery;
use App\Queries\TagStatsQuery;
use App\Services\Branches;
use App\Services\TagDnsVerification;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class TagController
{
    public function show(
        Request $request,
        string $tag,
        #[CurrentUser] ?User $user,
        Branches $hashtags,
        UpsertTags $upsertTags,
        HashtagCommitsQuery $commits,
        TagStatsQuery $stats,
        TagDnsVerification $verification,
    ): Response|RedirectResponse {
        $normalized = mb_strtolower($tag);

        if (! $hashtags->isValid($normalized)) {
            throw new NotFoundHttpException;
        }

        if ($tag !== $normalized) {
            return redirect()->route('tag.show', [
                'tag' => $normalized,
                ...$request->only('sort'),
            ], 301);
        }

        $upsertTags->handle([$normalized]);
        $tagModel = Tag::query()->where('name', $normalized)->with(['members.user'])->firstOrFail();
        $trending = $request->string('sort')->toString() === 'trending';
        $role = $user instanceof User ? $tagModel->roleOf($user) : null;

        return Inertia::render('tag/show', [
            'tag' => $this->payload($tagModel, $verification, $user, $role),
            'sort' => $trending ? 'trending' : 'latest',
            'isRanked' => $trending,
            'commits' => Inertia::scroll(fn () => $commits->builder($normalized, $user, $trending)->cursorPaginate(20)),
            'stats' => $stats->get($normalized),
        ]);
    }

    public function edit(
        Tag $tag,
        #[CurrentUser] User $user,
        TagDnsVerification $verification,
    ): Response {
        abort_unless($tag->isMaintainedBy($user) || $user->isAdmin(), 403);

        $tag->load(['members.user']);

        return Inertia::render('tag/settings', [
            'tag' => $this->payload($tag, $verification, $user, $tag->roleOf($user)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Tag $tag, TagDnsVerification $verification, ?User $user, ?TagRole $role): array
    {
        return [
            'name' => $tag->name,
            'description' => $tag->description,
            'domain_description' => $tag->domain_description,
            'brand_color' => $tag->isVerified() ? $tag->brand_color : null,
            'og_image_url' => $tag->isVerified() ? $tag->og_image_url : null,
            'domain' => $tag->domain,
            'verified_domain' => $tag->verified_domain,
            'is_verified' => $tag->isVerified(),
            'verification_pending' => $tag->hasPendingVerification(),
            'is_owned' => $tag->isOwned(),
            'can_claim' => $user instanceof User && ! $tag->isOwned() && Conversation::userMayVerifyBranch($user, $tag->name),
            'can_verify' => $user instanceof User && Conversation::userMayVerifyBranch($user, $tag->name),
            'org_locked' => Conversation::matchingBranch($tag->name)->exists(),
            'verification_host' => $tag->domain !== null ? $verification->hostFor($tag->domain) : null,
            'verification_record' => $tag->domain !== null && $tag->verification_token !== null
                ? $verification->recordValue($tag)
                : null,
            'viewer_role' => $role?->value,
            'is_watching' => $user instanceof User && $tag->watches()->where('user_id', $user->id)->exists(),
            'members' => $tag->relationLoaded('members')
                ? $tag->members
                    ->sortBy(fn ($member): array => [$member->role === TagRole::Maintainer ? 0 : 1, $member->user->username])
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
