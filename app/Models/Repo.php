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

    /**
     * Contributors, maintainers included, see the repo and its commits, and post to it.
     */
    public function hasContributor(User $user): bool
    {
        return $this->roleOf($user) instanceof RepoRole;
    }

    /**
     * Maintainers add and remove contributors, promote and demote them, and delete any commit in the repo.
     */
    public function isMaintainedBy(User $user): bool
    {
        return $this->roleOf($user) === RepoRole::Maintainer;
    }

    /**
     * A maintainer can only add people who follow them and aren't in the repo yet.
     */
    public function canAdd(User $maintainer, User $user): bool
    {
        return $user->isFollowing($maintainer) && ! $this->hasContributor($user);
    }

    /**
     * A repo always keeps a maintainer, so its last one can't be demoted. Leaving instead hands it to the
     * longest-standing contributor.
     */
    public function isLastMaintainer(User $user): bool
    {
        return $this->isMaintainedBy($user) && $this->contributors()->where('role', RepoRole::Maintainer)->count() === 1;
    }

    /**
     * On top of authors deleting their own, maintainers delete any commit in their repo.
     */
    public function canDeleteCommit(User $user, Commit $commit): bool
    {
        return $commit->repo_id === $this->id && $this->isMaintainedBy($user);
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
