<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MessageReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A private report on a message in remotes, which only moderators read. Its reasons are those of commit reports.
 *
 * @property-read string $id
 * @property-read int $message_id
 * @property-read string $reporter_id
 * @property-read string $reason
 * @property-read string|null $details
 * @property-read CarbonInterface|null $dismissed_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Message $message
 */
#[Visible(['id', 'reason', 'details', 'created_at', 'message'])]
#[Fillable(['message_id', 'reporter_id', 'reason', 'details'])]
final class MessageReport extends Model
{
    /** @use HasFactory<MessageReportFactory> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['message_id' => 'integer', 'dismissed_at' => 'datetime'];
    }

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
