<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\LiveStream;
use App\Enums\LivePlatform;
use Carbon\CarbonInterface;
use Database\Factories\GuildFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string|null $user_id
 * @property-read string|null $pinned_commit_id
 * @property-read string $handle
 * @property-read string $name
 * @property-read string $description
 * @property-read string $emoji
 * @property-read string|null $icon_path
 * @property-read LivePlatform|null $live_platform
 * @property-read string|null $live_url
 * @property-read CarbonInterface|null $live_ends_at
 * @property-read int $members_count
 * @property-read bool $is_joined
 * @property-read bool $is_muted
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Visible(['handle', 'name', 'description', 'emoji'])]
final class Guild extends Model
{
    /** @use HasFactory<GuildFactory> */
    use HasFactory;

    use HasUuids;

    /** @return HasMany<Commit, $this> */
    public function commits(): HasMany
    {
        return $this->hasMany(Commit::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guild_preferences')->wherePivotNotNull('joined_at');
    }

    /** @return BelongsToMany<User, $this> */
    public function muters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guild_preferences')->wherePivotNotNull('muted_at');
    }

    public function iconUrl(): ?string
    {
        return $this->icon_path === null ? null : route('guild-icon.show', ['guild' => $this->handle, 'v' => $this->updated_at->getTimestampMs()]);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'live_platform' => LivePlatform::class,
            'live_url' => 'string',
            'live_ends_at' => 'datetime',
        ];
    }

    /**
     * The stream the guild points at until it runs out, which also hides a stream the scheduler has not cleared yet.
     */
    public function liveStream(): ?LiveStream
    {
        return $this->live_ends_at?->isFuture() === true
            ? $this->live_platform?->driver()->stream((string) $this->live_url)
            : null;
    }

    /**
     * @return array{url: string, thumbnail_url: string, ends_at: string}|null
     */
    public function shownLive(): ?array
    {
        $stream = $this->liveStream();
        $endsAt = $this->live_ends_at;

        return $stream instanceof LiveStream && $endsAt !== null
            ? [...$stream->toArray(), 'ends_at' => $endsAt->toJSON()]
            : null;
    }
}
