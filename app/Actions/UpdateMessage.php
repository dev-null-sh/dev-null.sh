<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Jobs\UnfurlLink;
use App\Models\ConversationParticipant;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Queries\MentionedUsersQuery;
use App\Services\EmojiShortcodes;
use App\Services\Urls;
use Illuminate\Support\Facades\DB;

final readonly class UpdateMessage
{
    public function __construct(
        private EmojiShortcodes $emojiShortcodes,
        private MentionedUsersQuery $mentionedUsers,
        private Urls $urls,
    ) {
        //
    }

    /**
     * Rewords a message within its edit window: the body, mentions, and link card are recomputed the way pushing
     * computes them, everything else stays, and the thread hears about it so the partner sees the new wording live.
     */
    public function handle(Message $message, string $body): Message
    {
        $url = $this->urls->first($body);
        $sender = $message->user;

        DB::transaction(function () use ($message, $body, $url, $sender): void {
            // Read under a lock so overlapping edits serialize: the second one diffs against the first one's
            // committed mentions instead of a stale snapshot, and no badge is left behind.
            $previousMentions = array_values(Message::query()->whereKey($message->id)->lockForUpdate()->value('mentions') ?? []);
            $message->refresh();

            $linkPreview = $url === null ? null : LinkPreview::query()->firstOrCreate(['url_hash' => hash('sha256', $url)], ['url' => $url]);

            $message->update([
                'body' => $this->emojiShortcodes->replace($body),
                'mentions' => $this->mentionedUsers->get($body),
                'link_preview_id' => $linkPreview?->id,
                'edited_at' => now(),
            ]);

            if ($linkPreview instanceof LinkPreview && $linkPreview->fetched_at === null) {
                dispatch(new UnfurlLink($linkPreview))->afterCommit();
            }

            // Mention badges live on a pivot, not in the JSON. Badges are only added for newly mentioned
            // recipients and removed for deleted mentions, so a badge the recipient cleared stays cleared.
            if ($message->conversation->isOrg()) {
                $currentMentions = array_values($message->mentions ?? []);

                $message->mentionedUsers()->detach(array_diff($previousMentions, $currentMentions));

                $added = array_diff($currentMentions, $previousMentions);

                if ($added !== []) {
                    $message->mentionedUsers()->attach($message->conversation->participants()
                        ->whereIn('user_id', $added)
                        ->where('user_id', '!=', $sender->id)
                        ->where('visible_from_id', '<', $message->id)
                        ->whereNotIn('user_id', $sender->idsAcrossBlocks())
                        ->whereNotIn('user_id', $sender->mutedBy()->newPivotQuery()->select('muter_id'))
                        ->pluck('user_id'));
                }
            }
        });

        event(new UsersUpdated(ConversationParticipant::query()->where('conversation_id', $message->conversation_id)->pluck('user_id'), UserUpdate::Messages, ['conversation' => $message->conversation_id]));

        return $message->refresh()->load(Message::SHOWN_WITH);
    }
}
