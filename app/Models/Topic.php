<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read int $commits_count
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Hidden(['pivot'])]
final class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Every word Claude extracts is stored, but it only surfaces as a topic once this many commits share it,
     * so one-off words never clutter posts or get their own page.
     */
    public const int MIN_COMMITS = 5;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Commit, $this>
     */
    public function commits(): BelongsToMany
    {
        return $this->belongsToMany(Commit::class);
    }
}
