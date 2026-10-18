<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RepoRole;
use Carbon\CarbonInterface;
use Database\Factories\RepoContributorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $repo_id
 * @property-read string $user_id
 * @property-read RepoRole $role
 * @property-read User $user
 * @property-read Repo $repo
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class RepoContributor extends Model
{
    /** @use HasFactory<RepoContributorFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'contributor',
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'repo_id' => 'string',
            'user_id' => 'string',
            'role' => RepoRole::class,
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
     * @return BelongsTo<Repo, $this>
     */
    public function repo(): BelongsTo
    {
        return $this->belongsTo(Repo::class);
    }
}
