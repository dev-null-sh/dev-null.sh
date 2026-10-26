<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LinkPreview;
use App\Services\CodeSnippets;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A link's card, with the lines it cites when it is a GitHub permalink and they are still kept.
 *
 * @mixin LinkPreview
 */
final class LinkPreviewResource extends JsonResource
{
    /**
     * The card of a link that may not be there, as a commit or a message without one.
     */
    public static function maybe(?LinkPreview $preview): ?self
    {
        return $preview instanceof LinkPreview ? self::make($preview) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LinkPreview $preview */
        $preview = $this->resource;

        /** @var array<string, mixed> $card */
        $card = $preview->toArray();

        return [...$card, 'snippet' => resolve(CodeSnippets::class)->for($preview->url)];
    }
}
