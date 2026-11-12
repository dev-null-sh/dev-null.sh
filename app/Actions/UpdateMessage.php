<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
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
            $linkPreview = $url === null ? null : LinkPreview::query()->firstOrCreate(['url_hash' => hash('sha256', $url)], ['url' => $url]);

            $message->update([
                'body' => $this->emojiShortcodes->replace($body),
                'mentions' => $this->mentionedUsers->get($body),
                'link_preview_id' => $linkPreview?->id,
                'edited_at' => now(),
            ]);

            // Mention badges live on a pivot, not in the JSON, so resync them the way pushing does — but only where
            // pushing tracks them.
            if ($message->conversation->isOrg()) {
                $message->mentionedUsers()->sync($message->conversation->participants()
                    ->whereIn('user_id', array_values($message->mentions ?? []))
                    ->where('user_id', '!=', $sender->id)
                    ->where('visible_from_id', '<', $message->id)
                    ->whereNotIn('user_id', $sender->idsAcrossBlocks())
                    ->whereNotIn('user_id', $sender->mutedBy()->newPivotQuery()->select('muter_id'))
                    ->pluck('user_id'));
            }
        });

        event(new UsersUpdated(ConversationParticipant::query()->where('conversation_id', $message->conversation_id)->pluck('user_id'), UserUpdate::Messages, ['conversation' => $message->conversation_id]));

        return $message->refresh()->load(Message::SHOWN_WITH);
    }
}
