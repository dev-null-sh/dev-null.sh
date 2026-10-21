<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\Notification;
use App\Queries\MentionedUsersQuery;
use App\Services\CommitTranslations;
use App\Services\EmojiShortcodes;
use App\Services\Urls;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommit
{
    public function __construct(
        private EmojiShortcodes $emojiShortcodes,
        private MentionedUsersQuery $mentionedUsers,
        private Urls $urls,
        private CommitTranslations $translations,
        private CreateMentionNotifications $createMentionNotifications,
        private UpdateCommitHotScores $updateCommitHotScores,
    ) {
        //
    }

    public function handle(Commit $commit, string $body): void
    {
        DB::transaction(function () use ($commit, $body): void {
            $commit = Commit::query()->lockForUpdate()->findOrFail($commit->id);
            $body = $this->emojiShortcodes->replace($body);

            if ($commit->body === $body) {
                return;
            }

            Cache::forget($this->translations->key($commit));
            $user = $commit->user;
            assert($user !== null);
            $approved = $user->isAdmin() || $user->hasGoodReputation();
            $urls = $this->urls->all($body);
            $url = in_array($commit->linkPreview?->url, $urls, true) ? $commit->linkPreview->url : ($urls[0] ?? null);
            $preview = $url === null ? null : LinkPreview::query()->firstOrCreate(
                ['url_hash' => hash('sha256', $url)],
                ['url' => $url],
            );

            $commit->update([
                'body' => $body,
                'mentions' => $this->mentionedUsers->get($body),
                'status' => $approved ? CommitStatus::Approved : CommitStatus::Pending,
                'ai_decision' => null,
                'ai_categories' => null,
                'embedding' => null,
                'link_preview_id' => $preview?->id,
            ]);

            Notification::query()->where('commit_id', $commit->id)
                ->where('type', NotificationType::Mentioned)
                ->whereNotIn('recipient_id', array_values($commit->mentions ?? []))->delete();
            Notification::query()->where('commit_id', $commit->id)
                ->whereIn('type', [NotificationType::Approved, NotificationType::Rejected])->delete();
            $this->createMentionNotifications->handle($commit);
            $this->updateCommitHotScores->handle([$commit->id]);

            if ($preview instanceof LinkPreview && $preview->fetched_at === null) {
                dispatch(new UnfurlLink($preview))->afterCommit();
            }

            if (filled(config('services.openai.key'))) {
                if (! $approved) {
                    dispatch(new ModerateCommit($commit))->afterCommit();
                }

                if ($body !== '') {
                    dispatch(new EmbedCommit($commit))->afterCommit();
                }
            }
        });
    }
}
