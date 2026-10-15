<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LinkPreviewFactory;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read string $url
 * @property-read string $url_hash
 * @property-read string|null $title
 * @property-read string|null $description
 * @property-read string|null $image_url
 * @property-read string|null $site_name
 * @property-read CarbonInterface|null $fetched_at
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
            'fetched_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
