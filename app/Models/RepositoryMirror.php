<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $repository
 * @property-read string $repository_id
 * @property-read string $branch
 * @property-read bool $private
 * @property-read string $token
 * @property-read bool $mirror_commits
 * @property-read CarbonInterface|null $last_synced_at
 * @property-read string|null $last_error
 */
#[Hidden(['token'])]
final class RepositoryMirror extends Model
{
    use HasUuids;

    public function casts(): array
    {
        return ['token' => 'encrypted', 'private' => 'boolean', 'mirror_commits' => 'boolean', 'last_synced_at' => 'immutable_datetime'];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return $this->only(['id', 'repository', 'private', 'branch', 'mirror_commits', 'last_synced_at', 'last_error']);
    }
}
