<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CommitReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read string $reporter_id
 * @property-read string $reason
 * @property-read list<array{path: string, width: int, height: int, description: string|null, position: int}>|null $images
 * @property-read string|null $details
 * @property-read CarbonInterface|null $dismissed_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit|null $commit
 */
#[Hidden(['images'])]
#[Fillable(['commit_id', 'reporter_id', 'reason', 'details'])]
final class CommitReport extends Model
{
    /** @use HasFactory<CommitReportFactory> */
    use HasFactory;

    use HasUuids;

    public const array REASONS = [
        'spam' => 'Spam or misleading links',
        'harassment' => 'Harassment or hate',
        'unsafe' => 'Harmful or private information',
        'other' => 'Something else',
    ];

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['dismissed_at' => 'datetime', 'images' => 'encrypted:array'];
    }

    /** @return array<mixed> */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'attachments' => array_map(fn (array $image): array => [
                'url' => route('commit-report-image.show', ['report' => $this->id, 'image' => $image['position']]),
                'description' => $image['description'],
                'width' => $image['width'],
                'height' => $image['height'],
            ], $this->images ?? []),
        ];
    }

    /** @return BelongsTo<Commit, $this> */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }
}
