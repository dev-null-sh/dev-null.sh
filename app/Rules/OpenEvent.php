<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\EventState;
use App\Models\Event;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Responses are only taken until an event is over, and its host is going by definition.
 */
final readonly class OpenEvent implements ValidationRule
{
    public function __construct(private Event $event, private User $user)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->event->isHostedBy($this->user)) {
            $fail('You host this event.');
        } elseif (! in_array($this->event->state(), [EventState::Upcoming, EventState::Live], true)) {
            $fail('This event no longer takes responses.');
        }
    }
}
