<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GuildAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another hashtag that leads to a guild, beside its handle. No guild has it as its handle, and no other guild as an
 * alias.
 *
 * @property-read string $id
 * @property-read string $guild_id
 * @property-read string $handle
 * @property-read Guild $guild
 */
#[Visible(['handle'])]
final class GuildAlias extends Model
{
    /** @use HasFactory<GuildAliasFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * How many aliases one guild can have.
     */
    public const int MAX_PER_GUILD = 10;

    public $timestamps = false;

    /** @return BelongsTo<Guild, $this> */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(Guild::class);
    }
}
