<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrgEvent;
use Carbon\CarbonInterface;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bodies are encrypted at rest with the app key, so database dumps and backups don't expose them. Rotating the key
 * needs the old one in APP_PREVIOUS_KEYS, or older messages can no longer be read.
 *
 * @property-read int $id
 * @property-read string $conversation_id
 * @property-read string $user_id
 * @property-read string $client_id
 * @property-read string $body
 * @property-read OrgEvent|null $event
 * @property-read string|null $subject_id
 * @property-read User $user
 * @property-read User|null $subject
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Visible(['id', 'user_id', 'client_id', 'body', 'event', 'subject_id', 'created_at', 'user', 'subject'])]
final class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    public const int MAX_LENGTH = 2000;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'conversation_id' => 'string',
            'user_id' => 'string',
            'client_id' => 'string',
            'body' => 'encrypted',
            'event' => OrgEvent::class,
            'subject_id' => 'string',
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
     * The person an org event is about, like the one added or removed.
     *
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }
}
