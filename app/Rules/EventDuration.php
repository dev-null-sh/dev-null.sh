<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Event;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

final readonly class EventDuration implements ValidationRule
{
    public function __construct(private mixed $startsAt)
    {
        //
    }

    /**
     * An invalid start is reported on its own field, so only an end too far after a valid start fails here.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $startsAt = CarbonImmutable::parse(is_string($this->startsAt) ? $this->startsAt : '');
            $endsAt = CarbonImmutable::parse(is_string($value) ? $value : '');
        } catch (Throwable) {
            return;
        }

        if ($endsAt->greaterThan($startsAt->addDays(Event::MAX_DURATION_DAYS))) {
            $fail('An event lasts at most '.Event::MAX_DURATION_DAYS.' days.');
        }
    }
}
