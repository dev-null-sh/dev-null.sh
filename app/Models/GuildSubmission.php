<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\GuildSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guild, or an edit of one, that the moderation flagged, waiting for an admin to approve it. Nothing of it is
 * public until then.
 *
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $handle
 * @property-read string $name
 * @property-read string $description
 * @property-read string $emoji
 * @property-read string|null $icon_path
 * @property-read bool $remove_icon
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 */
#[Visible(['id'])]
final class GuildSubmission extends Model
{
    /** @use HasFactory<GuildSubmissionFactory> */
    use HasFactory;

    use HasUuids;

    /** @var array<string, mixed> */
    protected $attributes = ['remove_icon' => false];

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['name' => 'encrypted', 'description' => 'encrypted', 'remove_icon' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
