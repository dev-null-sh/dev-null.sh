<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\NewPoll;
use App\Enums\Feature;
use App\Enums\PollDuration;
use App\Models\ScheduledCommit;
use App\Services\Features;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final readonly class UpdateScheduledCommitSubmittedAt
{
    public function __construct(private CreateCommit $createCommit, private DeleteScheduledCommit $deleteScheduledCommit, private Features $features)
    {
        //
    }

    /**
     * The author can commit it now, before its scheduled time. One scheduled as an organization its user is no longer
     * a member of, or while posting as organizations is switched off, is canceled instead, as a ban cancels it.
     */
    public function handle(string $id, bool $early = false): bool
    {
        return DB::transaction(function () use ($id, $early): bool {
            $postable = fn (Builder $query): Builder => $query->whereNull('author_id')
                ->when($this->features->enabled(Feature::OrganizationCommits), fn (Builder $query): Builder => $query->orWhereExists(fn (QueryBuilder $members): QueryBuilder => $members
                    ->from('organization_members')
                    ->whereColumn('organization_members.organization_id', 'scheduled_commits.author_id')
                    ->whereColumn('organization_members.user_id', 'scheduled_commits.user_id')));

            // Claim with a conditional write before reading.
            // Creation and the claim roll back together if either operation fails.
            // Moderation is checked in the claim too, since a timeout or ban can land after the batch was selected...
            $claimed = ScheduledCommit::query()->whereKey($id)
                ->whereNull('submitted_at')->whereNull('canceled_at')
                ->unless($early, fn (Builder $query): Builder => $query->where('scheduled_at', '<=', now()))
                ->whereDoesntHave('user.timeouts', fn (Builder $query): Builder => $query->where('ends_at', '>', now()))
                ->whereDoesntHave('user.ban')
                ->where($postable)
                ->update(['submitted_at' => now()]);

            if ($claimed === 0) {
                $lost = ScheduledCommit::query()->with('user')->whereKey($id)->whereNot($postable)->first();

                if ($lost instanceof ScheduledCommit) {
                    $this->deleteScheduledCommit->handle($lost->user, $lost);
                }

                return false;
            }

            $scheduled = ScheduledCommit::query()->with(['user', 'author'])->findOrFail($id);
            assert(is_string($scheduled->body));
            $poll = $scheduled->poll === null ? null : new NewPoll($scheduled->poll['options'], PollDuration::from($scheduled->poll['duration']));
            $commit = $this->createCommit->handle($scheduled->user, $scheduled->body, linkPreview: $scheduled->link_preview_url ?? false, poll: $poll, organization: $scheduled->author?->organization);
            $commit->images()->createMany($scheduled->images ?? []);

            $scheduled->forceFill(['commit_id' => $commit->id, 'body' => null, 'poll' => null, 'images' => null, 'link_preview_url' => null])->save();

            return true;
        }, attempts: 5);
    }
}
