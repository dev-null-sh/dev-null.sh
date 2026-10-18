<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One person leaves a handful of different emoji on a message at most. Repeating one they already left still works.
 */
final readonly class MessageReactionLimit implements ValidationRule
{
    public function __construct(private Message $message, private User $user)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $emoji = MessageReaction::query()
            ->where('message_id', $this->message->id)
            ->where('user_id', $this->user->id)
            ->pluck('emoji');

        if ($emoji->count() >= MessageReaction::MAX_PER_MESSAGE && $emoji->doesntContain($value)) {
            $fail('You can leave up to '.MessageReaction::MAX_PER_MESSAGE.' reactions on a message.');
        }
    }
}
