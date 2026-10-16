<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ScheduledCommit;
use Illuminate\Support\Facades\DB;

final readonly class UpdateScheduledCommitSubmittedAt
{
    public function __construct(private CreateCommit $createCommit)
    {
        //
    }

    public function handle(string $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            // Claim with a conditional write before reading, including on SQLite.
            // Creation and the claim roll back together if either operation fails.
            $claimed = ScheduledCommit::query()->whereKey($id)
                ->whereNull('submitted_at')->whereNull('canceled_at')
                ->where('scheduled_at', '<=', now())
                ->update(['submitted_at' => now()]);

            if ($claimed === 0) {
                return false;
            }

            $scheduled = ScheduledCommit::query()->with('user')->findOrFail($id);
            assert(is_string($scheduled->body));
            $commit = $this->createCommit->handle($scheduled->user, $scheduled->body);

            $scheduled->forceFill(['commit_id' => $commit->id, 'body' => null])->save();

            return true;
        }, attempts: 5);
    }
}
