<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final readonly class UpdateConversationTyping
{
    /**
     * Long enough to bridge the composer's pings and the others' polls, short enough to vanish soon after typing stops.
     */
    public const int SECONDS = 5;

    public static function key(User $user, Conversation $conversation): string
    {
        return "conversation-typing:{$conversation->id}:{$user->id}";
    }

    /**
     * Only starting to type is announced: the others read the indicator again once it would have expired, and keep
     * seeing it while the pings keep it alive.
     */
    public function handle(User $user, Conversation $conversation): void
    {
        $started = ! Cache::has(self::key($user, $conversation));

        Cache::put(self::key($user, $conversation), true, self::SECONDS);

        if ($started) {
            event(new UsersUpdated(ConversationParticipant::query()->where('conversation_id', $conversation->id)->whereNot('user_id', $user->id)->pluck('user_id'), UserUpdate::Conversation, ['conversation' => $conversation->id]));
        }
    }
}
