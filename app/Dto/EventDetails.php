<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\EventFormat;
use App\Enums\StreamPlatform;
use App\Rules\EventEnd;
use App\Rules\EventStart;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeZone;
use Illuminate\Validation\Rule;

/**
 * When and where an event happens. Times arrive as wall-clock values in the host's time zone and are kept in UTC.
 */
final readonly class EventDetails
{
    public const string TIME_FORMAT = 'Y-m-d\TH:i';

    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $timezone,
        public EventFormat $format,
        public ?string $venue,
        public ?string $address,
        public ?StreamPlatform $platform,
        public ?string $joinUrl,
    ) {
        //
    }

    /**
     * Rules for the fields under `event`. A started event may keep its start time, so its place or link can still be fixed.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(?CarbonInterface $currentStart = null): array
    {
        return [
            'event.starts_at' => ['required_with:event', 'date_format:'.self::TIME_FORMAT, new EventStart($currentStart)],
            'event.ends_at' => ['required_with:event', 'date_format:'.self::TIME_FORMAT, new EventEnd],
            // "Factory" is a placeholder in the tz database for "zone not set yet": PHP lists it, but browsers throw when formatting a date in it.
            'event.timezone' => ['required_with:event', 'timezone:all_with_bc', 'not_in:Factory'],
            'event.format' => ['required_with:event', Rule::enum(EventFormat::class)],
            'event.venue' => ['required_if:event.format,onsite,hybrid', 'nullable', 'string', 'max:120'],
            'event.address' => ['required_if:event.format,onsite,hybrid', 'nullable', 'string', 'max:200'],
            'event.platform' => ['required_if:event.format,online,hybrid', 'nullable', Rule::enum(StreamPlatform::class)],
            'event.join_url' => ['required_if:event.format,online,hybrid', 'nullable', 'url:https', 'max:2048'],
        ];
    }

    /**
     * Fields that do not belong to the format are dropped.
     *
     * @param  array<mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $format = EventFormat::from(self::string($input, 'format'));
        $timezone = self::string($input, 'timezone');

        $startsAt = self::localTime(self::string($input, 'starts_at'), $timezone);
        $endsAt = self::localTime(self::string($input, 'ends_at'), $timezone);
        assert($startsAt instanceof CarbonImmutable && $endsAt instanceof CarbonImmutable);

        return new self(
            startsAt: $startsAt,
            endsAt: $endsAt,
            timezone: $timezone,
            format: $format,
            venue: $format->hasVenue() ? mb_trim(self::string($input, 'venue')) : null,
            address: $format->hasVenue() ? mb_trim(self::string($input, 'address')) : null,
            platform: $format->hasStream() ? StreamPlatform::from(self::string($input, 'platform')) : null,
            joinUrl: $format->hasStream() ? self::string($input, 'join_url') : null,
        );
    }

    /**
     * A wall-clock time in the given zone, in UTC, or null when either does not parse.
     */
    public static function localTime(mixed $value, mixed $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || ! is_string($timezone) || ! in_array($timezone, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC), true)) {
            return null;
        }

        try {
            $time = CarbonImmutable::createFromFormat(self::TIME_FORMAT, $value, $timezone);
        } catch (InvalidFormatException) {
            return null;
        }

        return $time instanceof CarbonImmutable ? $time->utc() : null;
    }

    /**
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, timezone: string, format: EventFormat, venue: string|null, address: string|null, platform: StreamPlatform|null, join_url: string|null}
     */
    public function toAttributes(): array
    {
        return [
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'timezone' => $this->timezone,
            'format' => $this->format,
            'venue' => $this->venue,
            'address' => $this->address,
            'platform' => $this->platform,
            'join_url' => $this->joinUrl,
        ];
    }

    /**
     * @param  array<mixed>  $input
     */
    private static function string(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
