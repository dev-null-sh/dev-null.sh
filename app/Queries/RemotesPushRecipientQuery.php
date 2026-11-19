<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\OrgEvent;
use App\Enums\RemoteStatus;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Who a message in a conversation should be pushed to, rechecked at delivery time: the participant who has not read
 * past it, has not muted the conversation, and is not muted or blocked by the sender. Browsers and phones ask apart,
 * since a recipient may have only one of the two.
 */
final readonly class RemotesPushRecipientQuery
{
    private function __construct(private bool $phones)
    {
        //
    }

    public static function forBrowsers(): self
    {
        return new self(false);
    }

    public static function forPhones(): self
    {
        return new self(true);
    }

    /**
     * Null when the message is too old, gone, or not a message or a ping, or when the recipient has no browser or
     * phone of this kind left.
     */
    public function get(Message $message, string $userId): ?ConversationParticipant
    {
        if (! in_array($message->event, [null, OrgEvent::Ping], true) || $message->created_at->lt(Date::now()->subMinutes(5))) {
            return null;
        }

        $participant = ConversationParticipant::query()->where('conversation_id', $message->conversation_id)
            ->where('user_id', $userId)
            ->where('user_id', '!=', $message->user_id)
            ->where('visible_from_id', '<', $message->id)
            ->where('read_through_id', '<', $message->id)
            ->whereIn('status', config('remotes.requests') ? [RemoteStatus::Open, RemoteStatus::Fetched] : [RemoteStatus::Open])
            ->where(fn (Builder $query): Builder => $query->whereNull('muted_until_at')->orWhere('muted_until_at', '<=', now()))
            ->whereHas('user', fn (Builder $query): Builder => $this->reachable($query))
            ->with('user')->first();

        if (! $participant instanceof ConversationParticipant) {
            return null;
        }

        $user = $participant->user;

        return ! $user->remotes_enabled || $user->hasBlockWith($message->user) || $user->isMuting($message->user) ? null : $participant;
    }

    /**
     * @param  Builder<User>  $users
     * @return Builder<User>
     */
    private function reachable(Builder $users): Builder
    {
        return $this->phones
            ? $users->whereHas('devices', fn (Builder $devices): Builder => $devices->whereNotNull('push_token_hash'))
            : $users->has('pushSubscriptions');
    }
}
