<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\TagRole;
use App\Models\Tag;
use App\Services\TagDnsVerification;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class PendingTagVerificationsQuery
{
    public function __construct(private TagDnsVerification $verification)
    {
        //
    }

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function paginate(): CursorPaginator
    {
        return Tag::query()
            ->whereNotNull('verification_requested_at')
            ->whereNull('verified_at')
            ->with(['members.user'])
            ->oldest('verification_requested_at')
            ->oldest('id')
            ->cursorPaginate(20)
            ->through(fn (Tag $tag): array => $this->payload($tag));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Tag $tag): array
    {
        $status = $this->verification->status($tag);

        return [
            'name' => $tag->name,
            'domain' => $tag->domain,
            'verification_requested_at' => $tag->verification_requested_at?->toIso8601String(),
            'txt' => $status,
            'maintainers' => $tag->members
                ->filter(fn ($member): bool => $member->role === TagRole::Maintainer)
                ->sortBy(fn ($member): string => $member->user->username)
                ->values()
                ->map(fn ($member): array => [
                    'username' => $member->user->username,
                    'name' => $member->user->name,
                    'avatar_url' => $member->user->avatar_url,
                    'lane' => $member->user->lane(),
                ])
                ->all(),
        ];
    }
}
