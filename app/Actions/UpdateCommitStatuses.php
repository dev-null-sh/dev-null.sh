<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\CommitUpdate;
use App\Enums\NotificationType;
use App\Enums\UserUpdate;
use App\Events\CommitsUpdated;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Jobs\CheckUserLevel;
use App\Jobs\LinkCommitTopics;
use App\Jobs\ProcessCommitHeadlines;
use App\Jobs\ReplyAsTom;
use App\Models\Commit;
use App\Models\Headline;
use App\Models\Notification;
use App\Models\Poll;
use App\Models\User;
use App\Queries\AdminsQuery;
use App\Queries\CommitBuilder;
use App\Queries\ProfileRecipientsQuery;
use App\Queries\TomQuery;
use Illuminate\Support\Collection;
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
        private ProfileRecipientsQuery $recipients,
        private AdminsQuery $admins,
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
                Headline::query()->whereIn('id', DB::table('commit_headline')->select('headline_id')->whereIn('commit_id', $commits->modelKeys()))
                    ->update(['summary' => null, 'summary_created_at' => null]);

                // Every member hears about an organization's rejected commit, each as their own actor, so nothing
                // tells them who posted it.
                /** @var Collection<int, object{profile_id: string, user_id: string, organization_id: string|null}> $recipients */
                $recipients = DB::query()->fromSub($this->recipients->builder(), 'recipients')
                    ->whereIn('profile_id', $commits->pluck('author_id')->filter()->unique()->values()->all())
                    ->get();

                $notifications = [];

                foreach ($commits as $commit) {
                    foreach ($recipients->where('profile_id', $commit->author_id) as $recipient) {
                        $notifications[] = [
                            'id' => Str::uuid7()->toString(),
                            'recipient_id' => $recipient->user_id,
                            'actor_id' => $recipient->user_id,
                            'organization_id' => $recipient->organization_id,
                            'type' => NotificationType::Rejected->value,
                            'commit_id' => $commit->id,
                            'key' => 'moderated:'.$commit->id,
                            'reason' => $reason,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                Notification::query()->insert($notifications);
            }

            $this->updateCommitHotScores->handle($commits->modelKeys());

            if ($status === CommitStatus::Approved) {
                Poll::query()
                    ->whereIn('commit_id', $commits->modelKeys())
                    ->whereNull('opened_at')
                    ->update(['opened_at' => now()]);

                // As when they are posted, only a person's first commit is announced.
                $personal = $commits->filter(fn (Commit $commit): bool => $commit->author_id === $commit->user_id);

                $this->createReplyNotifications->handle(...$commits);
                $this->createQuoteNotifications->handle(...$commits);
                $this->createMentionNotifications->handle(...$commits);
                $this->createFirstCommitNotifications->handle(...$personal);

                $commits->each(fn (Commit $commit) => $this->createWorldReply->handle($commit));
                LinkCommitTopics::dispatchFor(...$commits->all());
                ProcessCommitHeadlines::dispatchFor(...$commits->all());

                if (($tom = $this->tomQuery->get()) instanceof User) {
                    foreach ($commits as $commit) {
                        if ($commit->user?->github_id !== CreateTomFriendship::GITHUB_ID
                            && $commit->isAddressedTo($tom)) {
                            dispatch(new ReplyAsTom($commit->id));
                        }
                    }
                }

                User::query()->whereKey($personal->pluck('user_id')->filter()->unique()->values()->all())->get()
                    ->each(fn (User $author) => dispatch(new CheckUserLevel($author))->afterCommit());
            }

            // The commits still have the status they had, so those everyone saw before or sees now are announced.
            CommitsUpdated::dispatch($commits->filter(fn (Commit $commit): bool => $commit->status === CommitStatus::Approved || $status === CommitStatus::Approved), CommitUpdate::Visibility);

            $authors = $commits->pluck('user_id')->filter()->values()->all();
            UsersUpdated::dispatch($authors, UserUpdate::Commits);
            UsersUpdated::dispatch($authors, UserUpdate::ScheduledCommits);
            UsersUpdated::dispatch(Notification::query()->whereIn('commit_id', $commits->modelKeys())->distinct()->pluck('recipient_id')->all(), UserUpdate::Notifications);
            ProfilesUpdated::dispatch($commits->pluck('author_id'));
            UsersUpdated::dispatch($this->admins->ids(), UserUpdate::Moderation, ['commits' => array_values($commits->map(fn (Commit $commit): string => $commit->id)->all())]);
        });
    }
}
