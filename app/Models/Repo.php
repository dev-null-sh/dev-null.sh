<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RepoRole;
use Carbon\CarbonInterface;
use Database\Factories\RepoFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read string|null $description
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, RepoContributor> $contributors
 * @property-read Collection<int, Commit> $commits
 */
final class Repo extends Model
{
    /** @use HasFactory<RepoFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The user's role in the repo, or null when they don't contribute to it.
     */
    public function roleOf(User $user): ?RepoRole
    {
        return $this->contributors()->where('user_id', $user->id)->first()?->role;
    }

    public function isMaintainedBy(User $user): bool
    {
        return $this->roleOf($user) === RepoRole::Maintainer;
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'slug' => 'string',
            'description' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<RepoContributor, $this>
     */
    public function contributors(): HasMany
    {
        return $this->hasMany(RepoContributor::class);
    }

    /**
     * @return HasMany<Commit, $this>
     */
    public function commits(): HasMany
    {
        return $this->hasMany(Commit::class);
    }
}
