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
use Illuminate\Support\Facades\Storage;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read string $path
 * @property-read int $width
 * @property-read int $height
 * @property-read string|null $description
 * @property-read 'image/webp'|'video/webm' $mime_type
 * @property-read int|null $duration_ms
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
    'mime_type',
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
            'mime_type' => 'string',
            'duration_ms' => 'integer',
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
            'duration' => $this->duration_ms === null ? null : $this->duration_ms / 1000,
            'url' => $this->url(),
            'poster_url' => $this->isAnimation() ? route('commit-image.show', [$this, 'poster' => 1]) : null,
        ];
    }

    public function url(): string
    {
        return route('commit-image.show', $this);
    }

    public function publicUrl(bool $preview = false): string
    {
        return Storage::url($preview ? $this->previewPath() : $this->path);
    }

    public function isAnimation(): bool
    {
        return $this->mime_type === 'video/webm';
    }

    public function previewPath(): string
    {
        return match ($this->mime_type) {
            'image/webp' => $this->path,
            'video/webm' => $this->path.'.poster.webp',
        };
    }
}
