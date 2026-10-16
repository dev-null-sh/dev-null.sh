<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\CommitClaim;
use App\Models\CommitDecision;
use App\Models\Notification;
use App\Models\User;
use App\Queries\CommitBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpdateCommitStatuses
{
    public function __construct(
        private CreateReplyNotifications $createReplyNotifications,
        private CreateMentionNotifications $createMentionNotifications,
    ) {
        //
    }

    /**
     * Records the moderator's decision about each commit, and releases the claims on them.
     *
     * @param  array<int, string>  $commitIds
     */
    public function handle(User $moderator, array $commitIds, CommitStatus $status, ?string $reason = null, bool $onlyPending = false): void
    {
        assert($status !== CommitStatus::Pending);

        $reason = $status === CommitStatus::Rejected ? $reason : null;

        DB::transaction(function () use ($moderator, $commitIds, $status, $reason, $onlyPending): void {
            $commits = Commit::query()
                ->whereKey($commitIds)
                ->when($onlyPending, fn (CommitBuilder $query): CommitBuilder => $query->pending())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (Commit $commit): bool => $commit->status !== $status);

            if ($commits->isEmpty()) {
                return;
            }

            Commit::query()->whereKey($commits->modelKeys())->update(['status' => $status]);

            CommitDecision::query()->insert($commits->map(fn (Commit $commit): array => [
                'commit_id' => $commit->id,
                'user_id' => $moderator->id,
                'status' => $status->value,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            CommitClaim::query()->whereIn('commit_id', $commits->modelKeys())->delete();

            Notification::query()
                ->whereIn('commit_id', $commits->modelKeys())
                ->whereIn('type', [NotificationType::Approved, NotificationType::Rejected])
                ->delete();

            Notification::query()->insert($commits->map(fn (Commit $commit): array => [
                'id' => Str::uuid7()->toString(),
                'recipient_id' => $commit->user_id,
                'actor_id' => $commit->user_id,
                'type' => $status === CommitStatus::Approved ? NotificationType::Approved->value : NotificationType::Rejected->value,
                'commit_id' => $commit->id,
                'key' => 'moderated:'.$commit->id,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            if ($status === CommitStatus::Approved) {
                $this->createReplyNotifications->handle(...$commits);
                $this->createMentionNotifications->handle(...$commits);
            }
        });
    }
}
