<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\LinkPreview;

final readonly class LinkPreviewHealthQuery
{
    /**
     * A link preview that could not be fetched is still marked as fetched, only without any metadata.
     *
     * @return array{pending: int, failed: int}
     */
    public function get(): array
    {
        return [
            'pending' => LinkPreview::query()->whereNull('fetched_at')->count(),
            'failed' => LinkPreview::query()
                ->where('fetched_at', '>=', now()->subDay())
                ->whereNull(['title', 'description', 'image_url', 'site_name'])
                ->count(),
        ];
    }
}
