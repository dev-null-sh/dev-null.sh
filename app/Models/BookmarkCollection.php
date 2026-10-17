<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\BookmarkCollectionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A private, named group of saved commits. Only its owner ever sees it.
 *
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $name
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 * @property-read Collection<int, Bookmark> $bookmarks
 * @property-read int $commits_count
 */
final class BookmarkCollection extends Model
{
    /** @use HasFactory<BookmarkCollectionFactory> */
    use HasFactory;

    use HasUuids;

    public const int MAX_PER_USER = 50;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'name' => 'string',
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
     * @return BelongsToMany<Bookmark, $this>
     */
    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(Bookmark::class, 'bookmark_collection_items')->withTimestamps();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
