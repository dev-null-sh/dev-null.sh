<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CommitImageFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read string $path
 * @property-read int $width
 * @property-read int $height
 * @property-read string|null $description
 * @property-read int $position
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit $commit
 */
#[Visible([
    'id',
    'width',
    'height',
    'description',
])]
final class CommitImage extends Model
{
    /** @use HasFactory<CommitImageFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'commit_id' => 'string',
            'path' => 'string',
            'width' => 'integer',
            'height' => 'integer',
            'description' => 'string',
            'position' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Commit, $this>
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'url' => $this->url(),
        ];
    }

    public function url(): string
    {
        return route('commit-image.show', $this);
    }

    /**
     * Serves the image to anyone who has the URL, until it expires. The API hands it to the author of a pending commit,
     * since the mobile app has no web session to load their own pending images with. It stays the same for an hour at
     * a time, so the app can keep the image it loaded.
     */
    public function temporaryUrl(): string
    {
        return URL::temporarySignedRoute('commit-image.show', now()->startOfHour()->addHours(2), ['commitImage' => $this]);
    }
}
