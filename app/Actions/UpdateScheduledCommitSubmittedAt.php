<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\NewPoll;
use App\Enums\PollDuration;
use App\Models\ScheduledCommit;
use Illuminate\Database\Eloquent\Builder;
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
            // Claim with a conditional write before reading.
            // Creation and the claim roll back together if either operation fails.
            // Moderation is checked in the claim too, since a timeout or ban can land after the batch was selected...
            $claimed = ScheduledCommit::query()->whereKey($id)
                ->whereNull('submitted_at')->whereNull('canceled_at')
                ->where('scheduled_at', '<=', now())
                ->whereDoesntHave('user.timeouts', fn (Builder $query): Builder => $query->where('ends_at', '>', now()))
                ->whereDoesntHave('user.ban')
                ->update(['submitted_at' => now()]);

            if ($claimed === 0) {
                return false;
            }

            $scheduled = ScheduledCommit::query()->with('user')->findOrFail($id);
            assert(is_string($scheduled->body));
            $poll = $scheduled->poll === null ? null : new NewPoll($scheduled->poll['options'], PollDuration::from($scheduled->poll['duration']));
            $commit = $this->createCommit->handle($scheduled->user, $scheduled->body, linkPreview: $scheduled->link_preview_url ?? false, poll: $poll);
            $commit->images()->createMany($scheduled->images ?? []);

            $scheduled->forceFill(['commit_id' => $commit->id, 'body' => null, 'poll' => null, 'images' => null, 'link_preview_url' => null])->save();

            return true;
        }, attempts: 5);
    }
}
