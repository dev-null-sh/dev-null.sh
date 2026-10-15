<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property-read string $id
 * @property-read string $github_id
 * @property-read string $username
 * @property-read string $name
 * @property-read string|null $email
 * @property-read string|null $avatar_url
 * @property-read string|null $bio
 * @property-read string|null $location
 * @property-read string|null $website
 * @property-read string|null $company
 * @property-read string|null $remember_token
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, Commit> $commits
 * @property-read Collection<int, User> $following
 * @property-read Collection<int, User> $followers
 */
#[Hidden([
    'github_id',
    'email',
    'remember_token',
])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'github_id' => 'string',
            'username' => 'string',
            'name' => 'string',
            'email' => 'string',
            'avatar_url' => 'string',
            'bio' => 'string',
            'location' => 'string',
            'website' => 'string',
            'company' => 'string',
            'remember_token' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Commit, $this>
     */
    public function commits(): HasMany
    {
        return $this->hasMany(Commit::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'follower_id', 'followed_id')->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'follows', 'followed_id', 'follower_id')->withTimestamps();
    }

    /**
     * Users sign in with GitHub, so they have no password.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function isAdmin(): bool
    {
        /** @var list<string> $admins */
        $admins = config('app.admins');

        return in_array(mb_strtolower($this->username), array_map(mb_strtolower(...), $admins), true);
    }

    /**
     * Unlike loadCount(), counts follows on the pivot table alone: the self-referencing
     * relations would otherwise join the users table for every follow. Commits are
     * counted as the viewer sees them, which is all of them for the user themselves.
     */
    public function loadCounts(?self $viewer = null): self
    {
        $counts = [
            'commits_count' => $this->commits()->visibleTo($viewer ?? $this)->count(),
            'followers_count' => $this->followers()->newPivotQuery()->count(),
            'following_count' => $this->following()->newPivotQuery()->count(),
        ];

        $this->forceFill($counts)->syncOriginalAttributes(array_keys($counts));

        return $this;
    }

    public function isFollowing(self $user): bool
    {
        return $this->following()->whereKey($user->id)->exists();
    }
}
