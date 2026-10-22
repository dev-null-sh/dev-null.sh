<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedInteger;
use Carbon\CarbonInterface;
use Database\Factories\MessageVoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The file at the path is encrypted, and so are how long it plays and the waveform drawn from it.
 *
 * @property-read string $id
 * @property-read int $message_id
 * @property-read string $path
 * @property-read int $duration_ms
 * @property-read list<int> $waveform
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Message $message
 */
#[Visible([
    'id',
    'duration_ms',
    'waveform',
])]
final class MessageVoice extends Model
{
    /** @use HasFactory<MessageVoiceFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'message_id' => 'integer',
            'path' => 'string',
            'duration_ms' => EncryptedInteger::class,
            'waveform' => 'encrypted:array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function url(): string
    {
        return route('message-voice.show', $this);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'url' => $this->url(),
        ];
    }
}
