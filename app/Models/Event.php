<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventFormat;
use App\Enums\EventState;
use App\Enums\StreamPlatform;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read string $title
 * @property-read CarbonInterface $starts_at
 * @property-read CarbonInterface $ends_at
 * @property-read string $timezone
 * @property-read EventFormat $format
 * @property-read string|null $venue
 * @property-read string|null $address
 * @property-read StreamPlatform|null $platform
 * @property-read string|null $join_url
 * @property-read string|null $cover_path
 * @property-read int|null $cover_width
 * @property-read int|null $cover_height
 * @property-read int $sequence
 * @property-read CarbonInterface|null $details_changed_at
 * @property-read CarbonInterface|null $cancelled_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit $commit
 * @property-read Collection<int, User> $attendees
 */
final class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var array<string, int>
     */
    protected $attributes = [
        'sequence' => 0,
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'commit_id' => 'string',
            'title' => 'string',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'timezone' => 'string',
            'format' => EventFormat::class,
            'venue' => 'string',
            'address' => 'string',
            'platform' => StreamPlatform::class,
            'join_url' => 'string',
            'cover_path' => 'string',
            'cover_width' => 'integer',
            'cover_height' => 'integer',
            'sequence' => 'integer',
            'details_changed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_attendees')->withTimestamps();
    }

    /**
     * The count loaded with the event, or a query when it was not loaded.
     */
    public function attendeesCount(): int
    {
        $count = $this->getAttributes()['attendees_count'] ?? null;

        return is_numeric($count) ? (int) $count : $this->attendees()->count();
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Why the event no longer takes going or changes, or null while it is open.
     */
    public function closedReason(): ?string
    {
        if ($this->isCancelled()) {
            return __('This event was cancelled.');
        }

        if ($this->hasEnded()) {
            return __('This event has ended.');
        }

        return null;
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->isPast();
    }

    public function state(): EventState
    {
        return match (true) {
            $this->isCancelled() => EventState::Cancelled,
            $this->hasEnded() => EventState::Ended,
            $this->starts_at->isPast() => EventState::Live,
            default => EventState::Upcoming,
        };
    }

    /**
     * The venue and address, then the stream: its link, or the platform's name when there is none.
     */
    public function location(): string
    {
        $place = implode(', ', array_filter([$this->venue, $this->address]));

        $stream = match (true) {
            ! $this->format->hasStream() => null,
            $this->join_url !== null => $this->join_url,
            default => $this->platform?->label(),
        };

        return implode(' · ', array_filter([$place, $stream]));
    }

    /**
     * The url carries a version of the file, so a replaced cover is never served from a stale cache.
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public function cover(): ?array
    {
        if ($this->cover_path === null || $this->cover_width === null || $this->cover_height === null) {
            return null;
        }

        return [
            'url' => route('event-cover.show', ['event' => $this, 'v' => hash('xxh3', $this->cover_path)]),
            'width' => $this->cover_width,
            'height' => $this->cover_height,
        ];
    }

    /**
     * @return array{ics_url: string, apple_url: string, google_url: string, outlook_url: string}
     */
    public function calendarLinks(): array
    {
        $commit = $this->commit;
        $url = route('commit.show', [$commit->user, $commit]);
        $details = $commit->body."\n\n".$url;
        $location = $this->location();

        return [
            'ics_url' => route('event-calendar.show', $commit),
            'apple_url' => route('event-calendar.show', ['commit' => $commit, 'open' => 1]),
            'google_url' => 'https://calendar.google.com/calendar/render?'.http_build_query([
                'action' => 'TEMPLATE',
                'text' => $this->title,
                'dates' => $this->starts_at->copy()->utc()->format('Ymd\THis\Z').'/'.$this->ends_at->copy()->utc()->format('Ymd\THis\Z'),
                'details' => $details,
                'location' => $location,
            ], encoding_type: PHP_QUERY_RFC3986),
            'outlook_url' => 'https://outlook.live.com/calendar/0/deeplink/compose?'.http_build_query([
                'path' => '/calendar/action/compose',
                'rru' => 'addevent',
                'subject' => $this->title,
                'startdt' => $this->starts_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'enddt' => $this->ends_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'body' => $details,
                'location' => $location,
            ], encoding_type: PHP_QUERY_RFC3986),
        ];
    }

    /**
     * Events are only ever serialized for signed-in viewers: guests get the landing page, like for any commit.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $isForGuest = ($this->getAttributes()['is_for_guest'] ?? false) === true;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'starts_at' => $this->starts_at->copy()->utc()->toIso8601ZuluString(),
            'ends_at' => $this->ends_at->copy()->utc()->toIso8601ZuluString(),
            'timezone' => $this->timezone,
            'format' => $this->format->value,
            'venue' => $this->venue,
            'address' => $this->address,
            'platform' => $this->platform?->value,
            'platform_label' => $this->platform?->label(),
            'join_url' => $isForGuest ? null : $this->join_url,
            'cover' => $this->cover(),
            'attendees_count' => $this->attendeesCount(),
            'is_going' => (bool) ($this->getAttributes()['is_going'] ?? false),
            'state' => $this->state()->value,
            'updated' => $this->details_changed_at !== null,
            'calendar' => $isForGuest ? null : $this->calendarLinks(),
        ];
    }
}
