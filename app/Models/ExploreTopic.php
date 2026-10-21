<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $kind
 * @property-read string $key
 * @property-read string $title
 * @property-read list<string> $categories
 * @property-read string|null $summary
 * @property-read CarbonInterface|null $summary_generated_at
 * @property-read CarbonInterface|null $summary_dirty_at
 * @property-read int $summary_revision
 * @property-read CarbonInterface|null $suppressed_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read int $commits_count
 * @property-read int $contributors_count
 * @property-read float $score
 * @property-read CarbonInterface $latest_at
 */
#[Fillable(['kind', 'key', 'title', 'categories', 'summary', 'summary_generated_at', 'summary_dirty_at', 'suppressed_at'])]
#[Visible(['id', 'kind', 'key', 'title', 'categories'])]
final class ExploreTopic extends Model
{
    use HasUuids;

    public const array CATEGORIES = ['news' => 'News', 'ai' => 'AI', 'web-development' => 'Web Development', 'open-source' => 'Open Source'];

    public const array REASONS = [
        'irrelevant' => 'The associated content is not relevant',
        'spam' => 'This trend is spam',
        'abusive' => 'This trend is abusive or harmful',
        'not_interested' => 'Not interested in this',
        'duplicate' => 'This trend is a duplicate',
        'harmful_spammy' => 'This trend is harmful or spammy',
    ];

    public function casts(): array
    {
        return [
            'categories' => 'array',
            'summary_generated_at' => 'immutable_datetime',
            'summary_dirty_at' => 'immutable_datetime',
            'summary_revision' => 'integer',
            'suppressed_at' => 'immutable_datetime',
            'commits_count' => 'integer',
            'contributors_count' => 'integer',
            'score' => 'float',
            'latest_at' => 'immutable_datetime',
        ];
    }

    public function url(): string
    {
        return route('explore-topic.show', [$this->kind === 'subject' ? 'subjects' : 'stories', $this->key]);
    }
}
