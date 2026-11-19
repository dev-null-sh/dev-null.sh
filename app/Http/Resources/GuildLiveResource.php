<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dto\LiveStream;
use App\Models\Guild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The stream a guild points at as the guild page shows it, with when it runs out, so a page left open stops showing it then.
 *
 * @mixin Guild
 */
final class GuildLiveResource extends JsonResource
{
    /**
     * @return array{url: string, thumbnail_url: string, ends_at: string|null}|null
     */
    public static function of(Guild $guild): ?array
    {
        return $guild->liveStream() instanceof LiveStream ? new self($guild)->toArray(request()) : null;
    }

    /**
     * @return array{url: string, thumbnail_url: string, ends_at: string|null}
     */
    public function toArray(Request $request): array
    {
        $stream = $this->liveStream();
        assert($stream instanceof LiveStream);

        return [...$stream->toArray(), 'ends_at' => $this->live_ends_at?->toJSON()];
    }
}
