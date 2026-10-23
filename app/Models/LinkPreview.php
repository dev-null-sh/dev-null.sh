<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Embeds;
use Carbon\CarbonInterface;
use Database\Factories\LinkPreviewFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property-read string $id
 * @property-read string $url
 * @property-read string $url_hash
 * @property-read string|null $title
 * @property-read string|null $description
 * @property-read string|null $image_url
 * @property-read string|null $site_name
 * @property-read string|null $gif_url
 * @property-read CarbonInterface|null $fetched_at
 * @property-read CarbonInterface|null $unfurl_failed_at
 * @property-read int $publication_unfurl_retries
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Visible([
    'url',
    'title',
    'description',
    'image_url',
    'site_name',
])]
final class LinkPreview extends Model
{
    /** @use HasFactory<LinkPreviewFactory> */
    use HasFactory;

    use HasUuids;

    public const int MAX_PUBLICATION_UNFURL_RETRIES = 1;

    /** @var array<string, int> */
    protected $attributes = [
        'publication_unfurl_retries' => 0,
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'url' => 'string',
            'url_hash' => 'string',
            'title' => 'string',
            'description' => 'string',
            'image_url' => 'string',
            'site_name' => 'string',
            'gif_url' => 'string',
            'fetched_at' => 'datetime',
            'unfurl_failed_at' => 'datetime',
            'publication_unfurl_retries' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function prepareUnfurlForPublication(): bool
    {
        if ($this->fetched_at === null) {
            return true;
        }

        return $this->retryTransientFailureForPublication();
    }

    public function retryTransientFailureForPublication(): bool
    {
        return self::query()
            ->whereKey($this->id)
            ->whereNotNull('unfurl_failed_at')
            ->where('publication_unfurl_retries', '<', self::MAX_PUBLICATION_UNFURL_RETRIES)
            ->update([
                'fetched_at' => null,
                'unfurl_failed_at' => null,
                'publication_unfurl_retries' => DB::raw('publication_unfurl_retries + 1'),
            ]) === 1;
    }

    /**
     * @param  array<string, string|null>  $metadata
     */
    public function completeUnfurl(array $metadata): bool
    {
        return self::query()
            ->whereKey($this->id)
            ->where('publication_unfurl_retries', $this->publication_unfurl_retries)
            ->whereNull('fetched_at')
            ->update([...$metadata, 'fetched_at' => now(), 'unfurl_failed_at' => null]) === 1;
    }

    public function markUnfurlAsTransientFailure(int $publicationUnfurlRetries): void
    {
        self::query()
            ->whereKey($this->id)
            ->where('publication_unfurl_retries', $publicationUnfurlRetries)
            ->whereNull('fetched_at')
            ->update(['fetched_at' => now(), 'unfurl_failed_at' => now()]);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [...parent::toArray(), 'is_pending' => $this->fetched_at === null, 'embed' => resolve(Embeds::class)->for($this->url, $this->gif_url)?->toArray()];
    }
}
