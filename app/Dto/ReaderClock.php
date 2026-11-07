<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\DateFormat;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use IntlTimeZone;

/**
 * The time zone and hour cycle of the reader's browser, which it leaves in cookies so the server renders times the way
 * the browser shows them. Unknown until the browser has run once.
 *
 * @implements Arrayable<string, string|null>
 */
final readonly class ReaderClock implements Arrayable
{
    private const array HOUR_CYCLES = ['h11', 'h12', 'h23', 'h24'];

    public function __construct(
        public ?string $timeZone,
        public ?string $hourCycle,
        public ?string $dateOrder,
    ) {
        //
    }

    public static function of(Request $request): self
    {
        $timeZone = $request->cookie('time_zone');
        $hourCycle = $request->cookie('hour_cycle');
        $dateOrder = $request->cookie('date_order');
        $isSystemTimeZone = false;

        if (is_string($timeZone)) {
            // ICU recognizes browser aliases even when the OS omits legacy time zones.
            IntlTimeZone::getCanonicalID($timeZone, $isSystemTimeZone);
        }

        return new self(
            is_string($timeZone) && $isSystemTimeZone ? $timeZone : null,
            is_string($hourCycle) && in_array($hourCycle, self::HOUR_CYCLES, true) ? $hourCycle : null,
            is_string($dateOrder) ? DateFormat::tryFrom($dateOrder)?->value : null,
        );
    }

    /**
     * @return array{timeZone: string|null, hourCycle: string|null, dateOrder: string|null}
     */
    public function toArray(): array
    {
        return [
            'timeZone' => $this->timeZone,
            'hourCycle' => $this->hourCycle,
            'dateOrder' => $this->dateOrder,
        ];
    }
}
