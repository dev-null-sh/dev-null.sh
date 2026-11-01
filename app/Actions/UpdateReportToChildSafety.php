<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CommitReport;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;

final readonly class UpdateReportToChildSafety
{
    /**
     * Raises an open report to child safety, keeping its details and adding any new ones. Answers whether the reason
     * changed, which is when moderators need to hear about it. A dismissed report stays as it is.
     */
    public function handle(CommitReport|UserReport $report, ?string $details): bool
    {
        return DB::transaction(function () use ($report, $details): bool {
            $open = $report->newQuery()->whereKey($report->id)->whereNull('dismissed_at')->lockForUpdate()->first();

            if ($open === null) {
                return false;
            }

            $wasRaised = $open->reason !== CommitReport::CHILD_SAFETY;

            $open->forceFill([
                'reason' => CommitReport::CHILD_SAFETY,
                'details' => $this->details($open->details, $details),
            ])->save();

            return $wasRaised;
        });
    }

    private function details(?string $old, ?string $new): ?string
    {
        return match (true) {
            $old === null => $new,
            $new === null, str_contains($old, $new) => $old,
            default => "{$old}\n\n{$new}",
        };
    }
}
