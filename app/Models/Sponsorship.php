<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SponsorshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user sponsoring a maintainer on GitHub, kept only while the maintainer runs a sponsors org and both are on
 * /dev/null. It is read from GitHub whenever either of them signs in. A monthly sponsorship lapses when GitHub says it
 * ended; a one-time one, thirty days after the payment.
 *
 * @property-read int $id
 * @property-read string $sponsorable_id
 * @property-read string $sponsor_id
 * @property-read bool $is_one_time
 * @property-read CarbonInterface|null $lapses_at
 * @property-read CarbonInterface|null $left_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $sponsorable
 * @property-read User $sponsor
 */
final class Sponsorship extends Model
{
    /** @use HasFactory<SponsorshipFactory> */
    use HasFactory;

    /**
     * How long a one-time sponsorship counts as active.
     */
    public const int ONE_TIME_DAYS = 30;

    public function isActive(): bool
    {
        return $this->lapses_at === null || $this->lapses_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'sponsorable_id' => 'string',
            'sponsor_id' => 'string',
            'is_one_time' => 'boolean',
            'lapses_at' => 'datetime',
            'left_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sponsorable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsorable_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }
}
