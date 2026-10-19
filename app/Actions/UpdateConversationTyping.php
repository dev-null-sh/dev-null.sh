<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Conversation;
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

    public function handle(User $user, Conversation $conversation): void
    {
        Cache::put(self::key($user, $conversation), true, self::SECONDS);
    }
}
