<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dto\LiveStream;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The stream a person is on as the web shows it when it is asked for or heard of, with when it runs out, so a page
 * left open stops showing it then. Pages that show people get only the stream itself, and ask for this.
 *
 * @mixin User
 */
final class LiveStreamResource extends JsonResource
{
    /**
     * @return array{url: string, thumbnail_url: string, ends_at: string|null}|null
     */
    public static function of(User $user): ?array
    {
        return $user->liveStream() instanceof LiveStream ? (new self($user))->toArray(request()) : null;
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
