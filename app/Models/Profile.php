<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProfileType;
use Carbon\CarbonInterface;
use Database\Factories\ProfileFactory;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A GitHub account at its "@handle", mirrored from GitHub: what GitHub calls a profile owner. The handle is GitHub's
 * login, which can be renamed, so the account is matched by its GitHub ID.
 *
 * @property-read string $id
 * @property-read ProfileType $type
 * @property-read string $github_id
 * @property-read string $handle
 * @property-read string $name
 * @property-read string|null $avatar_url
 * @property-read string|null $bio
 * @property-read string|null $location
 * @property-read string|null $website
 * @property-read CarbonInterface|null $github_synced_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User|null $user
 */
#[Visible([
    'handle',
    'name',
    'avatar_url',
    'bio',
    'location',
    'website',
])]
#[Touches(['user'])]
final class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The GitHub identity a user shows, which lives on their profile.
     */
    public const array USER_ATTRIBUTES = ['handle', 'name', 'avatar_url', 'bio', 'location', 'website', 'github_synced_at'];

    /**
     * Organizations are refreshed from GitHub when a commit mentions them, at most this often.
     */
    public const int FRESH_FOR_HOURS = 24;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'type' => ProfileType::class,
            'github_id' => 'string',
            'handle' => 'string',
            'name' => 'string',
            'avatar_url' => 'string',
            'bio' => 'string',
            'location' => 'string',
            'website' => 'string',
            'github_synced_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Changes whenever anything drawn on the organization's link preview image may have changed.
     */
    public function openGraphImageVersion(): string
    {
        return hash('xxh3', (string) json_encode([$this->name, $this->handle, $this->avatar_url, $this->bio]));
    }

    /**
     * Resolves "@handle" URLs whatever the handle's case, like GitHub. A stale profile may still hold the same handle
     * in another case, so the exact case wins, then a user's, then always the same one, like users' pages.
     *
     * @param  Model|BuilderContract|Relation<Model, Model, mixed>  $query
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBindingQuery($query, $value, $field = null): BuilderContract
    {
        return $field === 'handle'
            ? $query->whereRaw('LOWER(handle) = LOWER(?)', [$value])->orderByRaw('handle = ? desc', [$value])->orderByRaw('type = ? desc', [ProfileType::User])->orderBy('handle')
            : parent::resolveRouteBindingQuery($query, $value, $field);
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'github_id', 'github_id');
    }

    /**
     * The users who claimed the organization's page. GitHub only lets them in: leaving the organization there doesn't
     * remove them here.
     *
     * @return BelongsToMany<User, $this>
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_admins', 'organization_id')->withTimestamps(updatedAt: false);
    }
}
