<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventResponse;
use App\Enums\EventState;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read string $title
 * @property-read CarbonInterface $starts_at
 * @property-read CarbonInterface $ends_at
 * @property-read string|null $location
 * @property-read string|null $url
 * @property-read CarbonInterface|null $cancelled_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read int|null $going_count
 * @property-read Commit|null $commit
 * @property-read User|null $host
 * @property-read Collection<int, EventAgendaItem> $agendaItems
 * @property-read Collection<int, EventAttendee> $attendees
 */
#[Visible(['id', 'commit_id', 'title', 'starts_at', 'ends_at', 'location', 'url', 'cancelled_at', 'going_count', 'agendaItems', 'commit', 'host'])]
final class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasUuids;

    public const int MAX_TITLE_LENGTH = 100;

    public const int MAX_LOCATION_LENGTH = 100;

    public const int MAX_URL_LENGTH = 255;

    public const int MAX_AGENDA_ITEMS = 12;

    public const int MAX_AGENDA_LABEL_LENGTH = 100;

    /**
     * Long enough for a conference or a hackathon week, short enough that an event is something you attend.
     */
    public const int MAX_DURATION_DAYS = 7;

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
            'location' => 'string',
            'url' => 'string',
            'cancelled_at' => 'datetime',
            'going_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [...parent::toArray(), 'state' => $this->state()];
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }

    /**
     * Whoever posted the announcement, for lists that show only who hosts rather than the whole commit.
     *
     * @return HasOneThrough<User, Commit, $this>
     */
    public function host(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, Commit::class, 'id', 'id', 'commit_id', 'user_id');
    }

    /**
     * @return HasMany<EventAgendaItem, $this>
     */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(EventAgendaItem::class)->oldest('starts_at')->orderBy('id');
    }

    /**
     * @return HasMany<EventAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function state(): EventState
    {
        return match (true) {
            $this->cancelled_at !== null => EventState::Cancelled,
            now()->greaterThanOrEqualTo($this->ends_at) => EventState::Ended,
            now()->greaterThanOrEqualTo($this->starts_at) => EventState::Live,
            default => EventState::Upcoming,
        };
    }

    /**
     * An event follows its announcement commit: visible once approved, to its host before, and gone with it.
     */
    public function isVisibleTo(?User $user): bool
    {
        return $this->commit instanceof Commit && $this->commit->isVisibleTo($user);
    }

    public function isHostedBy(User $user): bool
    {
        return $this->commit?->user_id === $user->id;
    }

    public function responseOf(?User $user): ?EventResponse
    {
        if (! $user instanceof User) {
            return null;
        }

        return $this->attendees()->where('user_id', $user->id)->first()?->response;
    }
}
