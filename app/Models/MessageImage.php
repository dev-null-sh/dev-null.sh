<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MessageImageFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read int $message_id
 * @property-read string $path
 * @property-read int $width
 * @property-read int $height
 * @property-read string|null $description
 * @property-read int $position
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Message $message
 */
#[Visible([
    'id',
    'width',
    'height',
    'description',
])]
final class MessageImage extends Model
{
    /** @use HasFactory<MessageImageFactory> */
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
            'width' => 'integer',
            'height' => 'integer',
            'description' => 'string',
            'position' => 'integer',
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

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'url' => route('message-image.show', $this),
        ];
    }
}
