<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Services\BranchDnsVerification;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class PendingBranchVerificationsQuery
{
    public function __construct(private BranchDnsVerification $verification)
    {
        //
    }

    /** @return CursorPaginator<int, array<string, mixed>> */
    public function paginate(): CursorPaginator
    {
        return Branch::query()
            ->whereNotNull('verification_requested_at')
            ->whereNull('verified_at')
            ->whereHas('members')
            ->with(['members.user'])
            ->oldest('verification_requested_at')
            ->oldest('id')
            ->cursorPaginate(20)
            ->through(fn (Branch $branch): array => $this->payload($branch));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Branch $branch): array
    {
        $status = $this->verification->status($branch);

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'domain' => $branch->domain,
            'verification_requested_at' => $branch->verification_requested_at?->toIso8601String(),
            'txt' => $status,
            'maintainers' => $branch->members
                ->filter(fn ($member): bool => $member->role === BranchRole::Maintainer)
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
