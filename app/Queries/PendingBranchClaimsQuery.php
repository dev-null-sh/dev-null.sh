<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Branch;
use Illuminate\Contracts\Pagination\CursorPaginator;

final readonly class PendingBranchClaimsQuery
{
    /** @return CursorPaginator<int, array<string, mixed>> */
    public function paginate(): CursorPaginator
    {
        return Branch::query()
            ->whereNotNull('claim_requested_at')
            ->whereNotNull('claim_requested_by')
            ->whereDoesntHave('members')
            ->with(['claimRequester'])
            ->oldest('claim_requested_at')
            ->oldest('id')
            ->cursorPaginate(20)
            ->through(fn (Branch $branch): array => $this->payload($branch));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Branch $branch): array
    {
        $requester = $branch->claimRequester;

        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'claim_requested_at' => $branch->claim_requested_at?->toIso8601String(),
            'requester' => $requester === null ? null : [
                'username' => $requester->username,
                'name' => $requester->name,
                'avatar_url' => $requester->avatar_url,
                'lane' => $requester->lane(),
            ],
        ];
    }
}
