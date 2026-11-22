<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommitVideoStatus;
use Carbon\CarbonInterface;
use Database\Factories\CommitVideoFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string|null $commit_id
 * @property-read CommitVideoStatus $status
 * @property-read int $size
 * @property-read string|null $description
 * @property-read int|null $width
 * @property-read int|null $height
 * @property-read float|null $duration
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit|null $commit
 */
#[Visible(['id', 'status', 'description', 'width', 'height', 'duration'])]
final class CommitVideo extends Model
{
    /** @use HasFactory<CommitVideoFactory> */
    use HasFactory;

    use HasUuids;

    public const int MAX_BYTES = 100 * 1024 * 1024;

    public const int MAX_SECONDS = 60;

    /** @return array<string, string> */
    public function casts(): array
    {
        return ['status' => CommitVideoStatus::class, 'size' => 'integer', 'description' => 'encrypted', 'width' => 'integer', 'height' => 'integer', 'duration' => 'float'];
    }

    /** @return BelongsTo<Commit, $this> */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(Commit::class);
    }

    public function path(string $file): string
    {
        return $file === 'source' ? 'video-uploads/'.$this->id : 'commit-videos/'.$this->id.'/'.$file;
    }

    /** @return array<mixed> */
    public function toArray(): array
    {
        return [
            ...parent::toArray(),
            'url' => $this->status === CommitVideoStatus::Ready ? route('commit-video-playback.show', $this) : null,
            'poster_url' => $this->status === CommitVideoStatus::Ready ? route('commit-video-playback.show', [$this, 'poster' => true]) : null,
        ];
    }
}
