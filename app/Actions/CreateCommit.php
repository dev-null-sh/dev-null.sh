<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\NewPoll;
use App\Enums\CommitStatus;
use App\Exceptions\CommitSourceChanged;
use App\Jobs\CheckUserLevel;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\ReplyAsTom;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use App\Queries\MentionedUsersQuery;
use App\Queries\TomQuery;
use App\Services\EmojiShortcodes;
use App\Services\TheAlgorithm;
use App\Services\Urls;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class CreateCommit
{
    public function __construct(
        private CreateReplyNotifications $createReplyNotifications,
        private CreateQuoteNotifications $createQuoteNotifications,
        private CreateMentionNotifications $createMentionNotifications,
        private CreateCommitImages $createCommitImages,
        private CreateWorldReply $createWorldReply,
        private UpdateCommitHotScores $updateCommitHotScores,
        private TheAlgorithm $theAlgorithm,
        private EmojiShortcodes $emojiShortcodes,
        private Urls $urls,
        private MentionedUsersQuery $mentionedUsers,
        private TomQuery $tomQuery,
    ) {
        //
    }

    /**
     * The link preview is of the first URL in the body when true, of the given URL when a string, or none when false.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $imageDescriptions
     */
    public function handle(User $user, string $body, ?Commit $parent = null, ?Commit $quotedCommit = null, array $images = [], array $imageDescriptions = [], bool|string $linkPreview = true, ?NewPoll $poll = null, ?string $sourceVersion = null): Commit
    {
        $url = match ($linkPreview) {
            true => $this->urls->first($body),
            false => null,
            default => $linkPreview,
        };

        return DB::transaction(function () use ($user, $body, $parent, $quotedCommit, $images, $imageDescriptions, $url, $poll, $sourceVersion): Commit {
            $sources = Commit::query()->whereKey(array_filter([$parent?->id, $quotedCommit?->id]))->orderBy('id')->lockForUpdate()->get();
            $expected = $parent ?? $quotedCommit;
            $current = $sources->first();

            if ($expected instanceof Commit && (! $current instanceof Commit
                || ! $current->isVisibleTo($user)
                || $current->version() !== $expected->version()
                || ($sourceVersion ?? $current->id) !== $current->version())) {
                throw new CommitSourceChanged;
            }

            foreach ($sources as $source) {
                if ($source->amendment_locked_at === null) {
                    $source->update(['amendment_locked_at' => now()]);
                }
            }

            $linkPreview = $this->linkPreviewFor($url);
            $createdAt = now();
            $hasGoodReputation = $user->hasGoodReputation();

            $commit = $user->commits()->create([
                'body' => $this->emojiShortcodes->replace($body),
                'mentions' => $this->mentionedUsers->get($body),
                'parent_id' => $parent?->id,
                'root_id' => $parent?->threadRootId(),
                'status' => $user->isAdmin() || $hasGoodReputation ? CommitStatus::Approved : CommitStatus::Pending,
                'link_preview_id' => $linkPreview?->id,
                'quoted_commit_id' => $quotedCommit?->id,
                'hot_score' => $this->theAlgorithm->score($createdAt, 0, 0),
                'created_at' => $createdAt,
            ]);

            $this->createCommitImages->handle($commit, $images, $imageDescriptions);

            if ($poll instanceof NewPoll) {
                $this->createPoll($commit, $poll);
            }

            if (($parent instanceof Commit || $quotedCommit instanceof Commit) && $commit->status === CommitStatus::Approved) {
                $this->updateCommitHotScores->handle([$commit->id]);
            }

            $this->createReplyNotifications->handle($commit);
            $this->createQuoteNotifications->handle($commit);
            $this->createMentionNotifications->handle($commit);

            if ($linkPreview instanceof LinkPreview && $linkPreview->fetched_at === null) {
                dispatch(new UnfurlLink($linkPreview));
            }

            $this->createWorldReply->handle($commit);

            if ($commit->status === CommitStatus::Approved
                && $user->github_id !== CreateTomFriendship::GITHUB_ID
                && filled(config('ai.providers.opencode.key')) && filled(config('ai.providers.openai.key'))
            ) {
                $tom = $this->tomQuery->get();

                if ($tom instanceof User && $commit->isAddressedTo($tom)) {
                    dispatch(new ReplyAsTom($commit->id));
                }
            }

            if ($commit->status === CommitStatus::Approved) {
                DB::afterCommit(fn (): PendingDispatch => dispatch(new CheckUserLevel($user)));
            }

            if ($commit->status === CommitStatus::Pending && ! $hasGoodReputation && filled(config('services.openai.key'))) {
                dispatch(new ModerateCommit($commit))->afterCommit();
            }

            if ($commit->body !== '' && filled(config('services.openai.key'))) {
                dispatch(new EmbedCommit($commit))->afterCommit();
            }

            return $commit;
        });
    }

    /**
     * A poll opens with its commit's approval, so polls of pending commits wait for moderation.
     */
    private function createPoll(Commit $commit, NewPoll $newPoll): void
    {
        $poll = $commit->poll()->create([
            'duration' => $newPoll->duration,
            'opened_at' => $commit->status === CommitStatus::Approved ? now() : null,
        ]);

        $poll->options()->createMany(array_map(
            fn (string $label, int $position): array => ['label' => $this->emojiShortcodes->replace($label), 'position' => $position],
            $newPoll->options,
            array_keys($newPoll->options),
        ));
    }

    /**
     * Commits that link to the same URL share one preview, so a page is fetched once however often it is linked.
     */
    private function linkPreviewFor(?string $url): ?LinkPreview
    {
        if ($url === null) {
            return null;
        }

        return LinkPreview::query()->firstOrCreate(
            ['url_hash' => hash('sha256', $url)],
            ['url' => $url],
        );
    }
}
