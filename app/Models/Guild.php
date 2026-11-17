<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\GuildFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $handle
 * @property-read string $name
 * @property-read string $description
 * @property-read string $emoji
 * @property-read string|null $icon_path
 * @property-read int $members_count
 * @property-read bool $is_joined
 * @property-read bool $is_muted
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Visible(['handle', 'name', 'description', 'emoji'])]
final class Guild extends Model
{
    /** @use HasFactory<GuildFactory> */
    use HasFactory;

    use HasUuids;

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guild_preferences')->wherePivotNotNull('joined_at');
    }

    /** @return BelongsToMany<User, $this> */
    public function muters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guild_preferences')->wherePivotNotNull('muted_at');
    }
}
