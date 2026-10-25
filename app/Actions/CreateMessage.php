<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RemoteStatus;
use App\Jobs\SendRemotePush;
use App\Jobs\UnfurlLink;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Models\User;
use App\Queries\MentionedUsersQuery;
use App\Services\EmojiShortcodes;
use App\Services\Urls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class CreateMessage
{
    public function __construct(
        private EmojiShortcodes $emojiShortcodes,
        private CreateImageFiles $createImageFiles,
        private CreateMessageVoiceFile $createMessageVoiceFile,
        private MentionedUsersQuery $mentionedUsers,
        private Urls $urls,
    ) {
        //
    }

    /**
     * Makes the message the conversation's latest, for every member's inbox.
     */
    public static function advance(Conversation $conversation, Message $message, bool $unstash): void
    {
        Conversation::query()
            ->whereKey($conversation->id)
            ->where(fn (Builder $query): Builder => $query->whereNull('last_message_id')->orWhere('last_message_id', '<', $message->id))
            ->update(['last_message_id' => $message->id]);

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->update(['last_message_at' => $message->created_at, ...$unstash ? ['archived_at' => null] : []]);
    }

    /**
     * Sending the same client id again, as a retry does, returns the message it already created, without storing its
     * images or voice note twice. A new message brings the conversation back out of the stash for everyone in it. The link card is
     * of the first link in the body when true, of the given link when a string, or none when false.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $imageDescriptions
     * @param  list<int>  $voiceWaveform
     */
    public function handle(User $sender, Conversation $conversation, string $body, string $clientId, ?Message $replyTo = null, array $images = [], array $imageDescriptions = [], bool|string $linkPreview = true, ?UploadedFile $voice = null, array $voiceWaveform = []): Message
    {
        $url = match ($linkPreview) {
            true => $this->urls->first($body),
            false => null,
            default => $linkPreview,
        };

        $message = DB::transaction(function () use ($sender, $conversation, $body, $clientId, $replyTo, $images, $imageDescriptions, $url, $voice, $voiceWaveform): Message {
            $linkPreview = $url === null ? null : LinkPreview::query()->firstOrCreate(['url_hash' => hash('sha256', $url)], ['url' => $url]);

            $message = Message::query()->createOrFirst(
                ['user_id' => $sender->id, 'client_id' => $clientId],
                [
                    'conversation_id' => $conversation->id,
                    'body' => $this->emojiShortcodes->replace($body),
                    'reply_to_id' => $replyTo?->id,
                    'mentions' => $this->mentionedUsers->get($body),
                    'link_preview_id' => $linkPreview?->id,
                ],
            );

            if (! $message->wasRecentlyCreated) {
                return $message;
            }

            $message->images()->createMany($this->createImageFiles->handle($images, $imageDescriptions, 'message-images', encrypted: true));

            if ($voice instanceof UploadedFile) {
                $message->voice()->create($this->createMessageVoiceFile->handle($voice, $voiceWaveform));
            }

            self::advance($conversation, $message, unstash: true);

            if (config('services.web_push.public_key') && config('services.web_push.private_key')) {
                foreach (ConversationParticipant::query()->where('conversation_id', $conversation->id)
                    ->where('user_id', '!=', $sender->id)->whereHas('user.pushSubscriptions')->get(['user_id']) as $participant) {
                    dispatch(new SendRemotePush($message->id, $participant->user_id))->delay(now()->addSeconds(10))->afterCommit();
                }
            }

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['status' => RemoteStatus::Open]);

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->where('read_through_id', '<', $message->id)
                ->update(['read_through_id' => $message->id, 'read_at' => now()]);

            if ($linkPreview instanceof LinkPreview && $linkPreview->fetched_at === null) {
                dispatch(new UnfurlLink($linkPreview))->afterCommit();
            }

            return $message;
        });

        Cache::forget(UpdateConversationTyping::key($sender, $conversation));

        return $message->load(Message::SHOWN_WITH);
    }
}
