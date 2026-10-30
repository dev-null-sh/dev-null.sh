<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ConversationKind;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\CommitReport;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageImage;
use App\Models\MessageVoice;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Queries\CommitBuilder;
use App\Services\CommitTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class DeleteUser
{
    public function __construct(private UpdateCommitHotScores $updateCommitHotScores, private DeleteOrgs $deleteOrgs)
    {
        //
    }

    /**
     * The user's top-level commits are erased with every reply under them, and so are their replies, except the ones
     * other people replied to: those stay behind as tombstones with no author or body, so the replies under them keep their thread.
     * The database cascades the image rows of erased commits and the user's scheduled commits, and the voice notes of their
     * messages, but the files are erased here. Orgs the user was the last one in go too, with everything sent in them,
     * as they do when the last member leaves.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $this->updateCommitHotScores->handle($this->engagedCommitIds($user));

            $translationKeys = $this->touchedCommits($user)
                ->select(['id', 'body'])
                ->lazyById(500)
                ->map(resolve(CommitTranslations::class)->key(...))->unique()->all();

            // Locked first, in statements of their own, so a topic lookup running meanwhile either links before this deletes its
            // links or finds the commit erased. The threads are locked before their replies, so no reply joins them in between.
            $user->commits()->withTrashed()->topLevel()->orderBy('id')->lockForUpdate()->pluck('id');
            $this->touchedCommits($user)->orderBy('id')->lockForUpdate()->pluck('id');

            // Report creation holds shared reporter and commit locks until its attachment paths are saved.
            $imagePaths = [...$this->reportImagePaths($user), ...$this->imagePaths($user), ...$this->scheduledImagePaths($user), ...$this->messageImagePaths($user), ...$this->messageVoicePaths($user)];
            resolve(DeleteStoredFiles::class)->retain($imagePaths);

            DB::table('commit_topics')->whereIn('commit_id', $this->touchedCommits($user)->select('id'))->delete();
            $user->commits()->withTrashed()->topLevel()->forceDelete();
            CommitImage::query()->whereIn('commit_id', $this->replies($user)->select('id'))->delete();
            CommitReport::query()->whereIn('commit_id', $this->replies($user)->select('id'))->delete();

            while ($this->replies($user)->whereDoesntHave('replies', fn (CommitBuilder $replies): CommitBuilder => $replies->withTrashed())->forceDelete() > 0) {
                //
            }

            $user->commits()->delete();
            $user->commits()->withTrashed()->update(['user_id' => null, 'body' => '', 'mentions' => null, 'embedding' => null, 'link_preview_id' => null]);

            $this->deleteOrgs->handle($this->emptyOrgs($user));

            $user->organizations()->detach();
            DB::table('user_active_days')->where('user_id', $user->id)->delete();

            $user->delete();
            $user->profile->delete();

            foreach ($translationKeys as $key) {
                Cache::forget($key);
            }
        });
    }

    /**
     * The commits the user starred, and their replies and quotes, whose threads rank lower once the user's stars,
     * replies and quotes are gone.
     *
     * @return array<int, string>
     */
    private function engagedCommitIds(User $user): array
    {
        return [
            ...$user->starredCommits()->get(['commits.id'])->map(fn (Commit $commit): string => $commit->id)->all(),
            ...$user->commits()
                ->withTrashed()
                ->where(fn (CommitBuilder $query): CommitBuilder => $query->whereNotNull('root_id')->orWhereNotNull('quoted_commit_id'))
                ->get(['id'])
                ->map(fn (Commit $commit): string => $commit->id)
                ->all(),
        ];
    }

    /**
     * The user's commits and every commit in the threads they started: all the erasure deletes or leaves as a tombstone.
     */
    private function touchedCommits(User $user): CommitBuilder
    {
        return Commit::query()->withTrashed()
            ->where(fn (CommitBuilder $query): CommitBuilder => $query->where('user_id', $user->id)
                ->orWhereIn('root_id', $user->commits()->withTrashed()->topLevel()->select('id')));
    }

    /**
     * @return HasMany<Commit, User>
     */
    private function replies(User $user): HasMany
    {
        return $user->commits()->withTrashed()->whereNotNull('parent_id');
    }

    /** @return array<int, string> */
    private function reportImagePaths(User $user): array
    {
        return CommitReport::query()
            ->where('reporter_id', $user->id)
            ->orWhereIn('commit_id', $user->commits()->withTrashed()->select('id'))
            ->orWhereIn('commit_id', Commit::query()->withTrashed()->whereIn('root_id', $user->commits()->withTrashed()->topLevel()->select('id'))->select('id'))
            ->get(['images'])
            ->flatMap(fn (CommitReport $report): array => array_column($report->images ?? [], 'path'))
            ->all();
    }

    /**
     * The images of the user's commits and of every reply in the threads they started, which go along with them.
     *
     * @return array<int, string>
     */
    private function imagePaths(User $user): array
    {
        return CommitImage::query()
            ->whereIn('commit_id', $user->commits()->withTrashed()->select('id'))
            ->orWhereIn('commit_id', Commit::query()->withTrashed()->whereIn('root_id', $user->commits()->withTrashed()->topLevel()->select('id'))->select('id'))
            ->get(['path'])
            ->map(fn (CommitImage $image): string => $image->path)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function scheduledImagePaths(User $user): array
    {
        return ScheduledCommit::query()
            ->where('user_id', $user->id)
            ->whereNotNull('images')
            ->get(['images'])
            ->flatMap(fn (ScheduledCommit $scheduled): array => array_column($scheduled->images ?? [], 'path'))
            ->all();
    }

    /**
     * The images the user sent in remotes, whose messages go along with them.
     *
     * @return array<int, string>
     */
    private function messageImagePaths(User $user): array
    {
        return MessageImage::query()
            ->whereIn('message_id', Message::query()->where('user_id', $user->id)->select('id'))
            ->get(['path'])
            ->map(fn (MessageImage $image): string => $image->path)
            ->all();
    }

    /**
     * The voice notes the user sent in remotes, whose messages go along with them.
     *
     * @return array<int, string>
     */
    private function messageVoicePaths(User $user): array
    {
        return MessageVoice::query()
            ->whereIn('message_id', Message::query()->where('user_id', $user->id)->select('id'))
            ->get(['path'])
            ->map(fn (MessageVoice $voice): string => $voice->path)
            ->all();
    }

    /**
     * @return Builder<Conversation>
     */
    private function emptyOrgs(User $user): Builder
    {
        return Conversation::query()
            ->where('kind', ConversationKind::Org)
            ->whereHas('participants', fn (Builder $query): Builder => $query->where('user_id', $user->id))
            ->whereDoesntHave('participants', fn (Builder $query): Builder => $query->where('user_id', '!=', $user->id));
    }
}
