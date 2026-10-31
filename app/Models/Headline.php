<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\HeadlineFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Collection<int, Commit> $commits
 */
#[Visible(['name'])]
final class Headline extends Model
{
    /** @use HasFactory<HeadlineFactory> */
    use HasFactory;

    use HasUuids;
    use MassPrunable;

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
        return $this->belongsToMany(Commit::class)->withTimestamps(updatedAt: false);
    }

    /**
     * A headline expires fourteen days after its last new link, or its creation if never linked. Pivot creation
     * timestamps measure links rather than edits to headlines or commits, including commits in their undo window.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $cutoff = now()->subDays(14);

        return self::query()->where('created_at', '<=', $cutoff)
            ->whereNotExists(fn (QueryBuilder $query): QueryBuilder => $query->selectRaw('1')->from('commit_headline')
                ->whereColumn('commit_headline.headline_id', 'headlines.id')
                ->where('commit_headline.created_at', '>', $cutoff));
    }
}
