<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrgRole;
use App\Enums\RemoteStatus;
use Carbon\CarbonInterface;
use Database\Factories\ConversationParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property-read string $conversation_id
 * @property-read string $user_id
 * @property-read RemoteStatus $status
 * @property-read OrgRole|null $role
 * @property-read int $visible_from_id
 * @property-read CarbonInterface|null $last_message_at
 * @property-read int $read_through_id
 * @property-read User $user
 * @property-read CarbonInterface|null $read_at
 * @property-read CarbonInterface|null $archived_at
 * @property-read CarbonInterface|null $pinned_at
 * @property-read CarbonInterface|null $muted_until_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class ConversationParticipant extends Model
{
    /** @use HasFactory<ConversationParticipantFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'visible_from_id' => 0,
        'read_through_id' => 0,
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'conversation_id' => 'string',
            'user_id' => 'string',
            'status' => RemoteStatus::class,
            'role' => OrgRole::class,
            'visible_from_id' => 'integer',
            'last_message_at' => 'datetime',
            'read_through_id' => 'integer',
            'read_at' => 'datetime',
            'archived_at' => 'datetime',
            'pinned_at' => 'datetime',
            'muted_until_at' => 'datetime',
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
}
