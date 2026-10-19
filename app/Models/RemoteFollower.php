<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RemoteFollowerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $user_id
 * @property-read string $actor_url
 * @property-read string $inbox_url
 * @property-read string|null $shared_inbox_url
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 */
final class RemoteFollower extends Model
{
    /** @use HasFactory<RemoteFollowerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'string',
            'actor_url' => 'string',
            'inbox_url' => 'string',
            'shared_inbox_url' => 'string',
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

    public function deliveryInbox(): string
    {
        return $this->shared_inbox_url ?? $this->inbox_url;
    }
}
