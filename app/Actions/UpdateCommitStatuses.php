<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Jobs\CheckUserLevel;
use App\Jobs\LinkCommitTopics;
use App\Jobs\ReplyAsTom;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Queries\TomQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class UpdateCommitStatuses
{
    public function __construct(
        private CreateReplyNotifications $createReplyNotifications,
        private CreateQuoteNotifications $createQuoteNotifications,
        private CreateMentionNotifications $createMentionNotifications,
        private CreateFirstCommitNotifications $createFirstCommitNotifications,
        private CreateWorldReply $createWorldReply,
        private UpdateCommitHotScores $updateCommitHotScores,
        private TomQuery $tomQuery,
    ) {
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

            if ($status === CommitStatus::Rejected) {
                // An organization's commit whose poster has since deleted their account has no one to tell.
                Notification::query()->insert($commits->whereNotNull('user_id')->map(fn (Commit $commit): array => [
                    'id' => Str::uuid7()->toString(),
                    'recipient_id' => $commit->user_id,
                    'actor_id' => $commit->user_id,
                    'type' => NotificationType::Rejected->value,
                    'commit_id' => $commit->id,
                    'key' => 'moderated:'.$commit->id,
                    'reason' => $reason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all());
            }

            $this->updateCommitHotScores->handle($commits->modelKeys());

            if ($status === CommitStatus::Approved) {
                Poll::query()
                    ->whereIn('commit_id', $commits->modelKeys())
                    ->whereNull('opened_at')
                    ->update(['opened_at' => now()]);

                // As when they are posted, an organization's commits notify no one yet, and get no welcome or answer.
                $personal = $commits->filter(fn (Commit $commit): bool => $commit->author_id === $commit->user_id);

                $this->createReplyNotifications->handle(...$personal);
                $this->createQuoteNotifications->handle(...$personal);
                $this->createMentionNotifications->handle(...$personal);
                $this->createFirstCommitNotifications->handle(...$personal);

                $personal->each(fn (Commit $commit) => $this->createWorldReply->handle($commit));
                LinkCommitTopics::dispatchFor(...$commits->all());

                if (($tom = $this->tomQuery->get()) instanceof User) {
                    foreach ($personal as $commit) {
                        if ($commit->user?->github_id !== CreateTomFriendship::GITHUB_ID
                            && $commit->isAddressedTo($tom)) {
                            dispatch(new ReplyAsTom($commit->id));
                        }
                    }
                }

                User::query()->whereKey($personal->pluck('user_id')->filter()->unique()->values()->all())->get()
                    ->each(fn (User $author) => dispatch(new CheckUserLevel($author))->afterCommit());
            }
        });
    }
}
