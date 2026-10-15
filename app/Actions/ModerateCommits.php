<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Queries\CommitBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ModerateCommits
{
    public function __construct(private NotifyAboutReplies $notifyAboutReplies)
    {
        //
    }

    /**
     * @param  array<int, string>  $commitIds
     */
    public function handle(array $commitIds, CommitStatus $status, ?string $reason = null, bool $onlyPending = false): void
    {
        assert($status !== CommitStatus::Pending);

        DB::transaction(function () use ($commitIds, $status, $reason, $onlyPending): void {
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
                'reason' => $status === CommitStatus::Rejected ? $reason : null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            if ($status === CommitStatus::Approved) {
                $this->notifyAboutReplies->handle(...$commits);
            }
        });
    }
}
