<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\GuildReportFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $guild_id
 * @property-read string $reporter_id
 * @property-read string $details
 * @property-read CarbonInterface|null $dismissed_at
 * @property-read CarbonInterface $created_at
 * @property-read Guild $guild
 */
#[Visible(['id'])]
final class GuildReport extends Model
{
    /** @use HasFactory<GuildReportFactory> */
    use HasFactory;

    use HasUuids;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['details' => 'encrypted', 'dismissed_at' => 'datetime'];
    }

    /** @return BelongsTo<Guild, $this> */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }
}
