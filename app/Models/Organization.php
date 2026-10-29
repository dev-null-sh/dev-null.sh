<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationRole;
use Carbon\CarbonInterface;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

/**
 * A GitHub organization on /dev/null: what only organizations have, like their members and their color. Its GitHub
 * identity lives on its profile, whose ID it shares, as users' lives on theirs.
 *
 * @property-read string $id
 * @property-read string $handle
 * @property-read string $name
 * @property-read string|null $avatar_url
 * @property-read string|null $bio
 * @property-read string|null $location
 * @property-read string|null $website
 * @property-read CarbonInterface|null $github_synced_at
 * @property-read int|null $preferred_lane
 * @property-read string|null $lane_color
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Profile $profile
 */
#[Visible([
    'handle',
    'name',
    'avatar_url',
    'bio',
    'location',
    'website',
])]
final class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Organizations are refreshed from GitHub when a commit mentions them, at most this often.
     */
    public const int FRESH_FOR_HOURS = 24;

    /**
     * Every organization shows its GitHub identity, so its profile comes with it.
     *
     * @var list<string>
     */
    protected $with = ['profile'];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'preferred_lane' => 'integer',
            'lane_color' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'id');
    }

    /**
     * Changes whenever anything drawn on the organization's link preview image may have changed.
     */
    public function openGraphImageVersion(): string
    {
        return hash('xxh3', (string) json_encode([$this->name, $this->handle, $this->avatar_url, $this->bio, $this->lane()]));
    }

    /**
     * The lane the organization's admins chose, like a user's, or null for /dev/null's blue, which organizations have
     * until then.
     */
    public function lane(): int|string|null
    {
        return $this->lane_color ?? $this->preferred_lane;
    }

    /**
     * The organization's members on /dev/null, each an admin or a member. GitHub only lets the first admin in, who
     * claimed the page: leaving the organization there doesn't remove anyone here.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_members', 'organization_id')->withPivot('role')->withTimestamps(updatedAt: false);
    }

    /**
     * The members who manage the organization's members. Attaching through it makes an admin.
     *
     * @return BelongsToMany<User, $this>
     */
    public function admins(): BelongsToMany
    {
        return $this->members()->withPivotValue('role', OrganizationRole::Admin->value);
    }

    /**
     * The users who follow the organization, whether they are its members or not.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_follows', 'organization_id')->withTimestamps(updatedAt: false);
    }

    /**
     * The user's role in the organization, or null when they aren't a member.
     */
    public function roleOf(User $user): ?OrganizationRole
    {
        $role = $this->members()->newPivotQuery()->where('user_id', $user->id)->value('role');

        return is_string($role) ? OrganizationRole::from($role) : null;
    }

    /**
     * Whether the user is one of the organization's members, admin or not.
     */
    public function isMember(User $user): bool
    {
        return $this->roleOf($user) instanceof OrganizationRole;
    }

    public function isAdmin(User $user): bool
    {
        return $this->roleOf($user) === OrganizationRole::Admin;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'location' => $this->location,
            'website' => $this->website,
            ...parent::toArray(),
        ];
    }

    /**
     * The GitHub identity lives on the profile: it reads through, and only the profile changes it.
     *
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        return in_array($key, Profile::USER_ATTRIBUTES, true) ? $this->profile->getAttribute($key) : parent::getAttribute($key);
    }

    /**
     * @param  string  $key
     */
    public function setAttribute($key, $value): mixed
    {
        throw_if(in_array($key, Profile::USER_ATTRIBUTES, true), LogicException::class, "Update the {$key} of an organization on its profile.");

        return parent::setAttribute($key, $value);
    }
}
