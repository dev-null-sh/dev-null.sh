<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A private group of developers whose commits its owner reads as a log of their own.
 *
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $name
 * @property-read CarbonInterface|null $pinned_at
 * @property-read int $members_count
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 */
#[Fillable(['name', 'pinned_at'])]
#[Visible(['id', 'name', 'pinned_at', 'members_count'])]
final class UserList extends Model
{
    /** @use HasFactory<UserListFactory> */
    use HasFactory;

    use HasUuids;

    public const int MAX_NAME_LENGTH = 40;

    public const int MAX_PER_USER = 10;

    public const int MAX_MEMBERS = 250;

    /**
     * Pinned lists sit next to the logs as tabs, so only a few fit.
     */
    public const int MAX_PINNED = 2;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'name' => 'string',
            'pinned_at' => 'immutable_datetime',
            'members_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_list_members')->withTimestamps();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }
}
