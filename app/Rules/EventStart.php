<?php

declare(strict_types=1);

namespace App\Rules;

use App\Dto\EventDetails;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An event starts in the future, unless it already started and keeps that start.
 */
final class EventStart implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(private readonly ?CarbonInterface $currentStart = null)
    {
        //
    }

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
        $startsAt = EventDetails::localTime($value, data_get($this->data, 'event.timezone'));

        if (! $startsAt instanceof CarbonImmutable || ! $startsAt->isPast()) {
            return;
        }

        if ($this->currentStart instanceof CarbonInterface && $startsAt->equalTo($this->currentStart->copy()->utc()->startOfMinute())) {
            return;
        }

        $fail(__('The event must start in the future.'));
    }
}
