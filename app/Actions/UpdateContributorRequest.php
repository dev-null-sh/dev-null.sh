<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ContributorRequestStatus;
use App\Models\ContributorRequest;

final readonly class UpdateContributorRequest
{
    public function handle(ContributorRequest $request, ContributorRequestStatus $status): void
    {
        // Change availability, never the already moderated commit or project description.
        ContributorRequest::query()->whereKey($request->id)->where('status', '!=', $status)->update([
            'status' => $status,
            'filled_at' => $status === ContributorRequestStatus::Filled ? now() : null,
        ]);
        $request->refresh();
    }
}
