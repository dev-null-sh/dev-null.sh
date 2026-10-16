<?php

declare(strict_types=1);

namespace App\Rules;

use App\Dto\EventDetails;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An event ends in the future, after it starts, and lasts at most 7 days.
 */
final class EventEnd implements DataAwareRule, ValidationRule
{
    private const int MAX_MINUTES = 7 * 24 * 60;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $timezone = data_get($this->data, 'event.timezone');
        $endsAt = EventDetails::localTime($value, $timezone);
        $startsAt = EventDetails::localTime(data_get($this->data, 'event.starts_at'), $timezone);

        if (! $endsAt instanceof CarbonImmutable || ! $startsAt instanceof CarbonImmutable) {
            return;
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $fail(__('The event must end after it starts.'));
        } elseif ($startsAt->diffInMinutes($endsAt) > self::MAX_MINUTES) {
            $fail(__('An event can last at most 7 days.'));
        } elseif ($endsAt->isPast()) {
            $fail(__('The event must end in the future.'));
        }
    }
}
