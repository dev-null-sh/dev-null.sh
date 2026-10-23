<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GifProvider;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read GifProvider $provider
 * @property-read string $provider_gif_id
 * @property-read string $url
 * @property-read string $page_url
 * @property-read string|null $title
 * @property-read string|null $username
 * @property-read int $width
 * @property-read int $height
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Commit $commit
 */
final class CommitGif extends Model
{
    /** @use HasFactory<Factory<CommitGif>> */
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
            'provider' => GifProvider::class,
            'provider_gif_id' => 'string',
            'url' => 'string',
            'page_url' => 'string',
            'title' => 'string',
            'username' => 'string',
            'width' => 'integer',
            'height' => 'integer',
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
     * The GIF as every read surface exposes it.
     *
     * @return array{provider: string, id: string, url: string, page_url: string, title: string|null, username: string|null, width: int, height: int}
     */
    public function toPayload(): array
    {
        return [
            'provider' => $this->provider->value,
            'id' => $this->provider_gif_id,
            'url' => $this->url,
            'page_url' => $this->page_url,
            'title' => $this->title,
            'username' => $this->username,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
