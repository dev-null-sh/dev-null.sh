<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Dto\LiveStream;
use App\Enums\LivePlatform;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user's public profile, with their counts when they were loaded. A signed request also gets the lane, the color
 * the web draws them in, and in a list of people, whether its user follows each one and whether each one follows them.
 * Who to follow also says how many people the user follows follow each one.
 *
 * Wherever a user shows up, live is the stream they are on until it runs out, or null, so an app can mark who is live
 * in a list without asking for each profile.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'handle' => $this->handle,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'company' => $this->company,
            'location' => $this->location,
            'website' => $this->website,
            'github_url' => "https://github.com/{$this->handle}",
            'html_url' => route('user.show', $this->resource),
            'feed_url' => route('user-feed.show', $this->resource),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'live' => $this->live(),
            'commits_count' => $this->whenCounted('commits'),
            'followers_count' => $this->whenCounted('followers'),
            'following_count' => $this->whenCounted('following'),
            'lane' => $this->when($request->user() instanceof User, fn (): int|string => $this->lane()),
            'is_followed' => $this->whenHas('is_followed'),
            'follows_you' => $this->whenHas('follows_you'),
            'followed_by_following_count' => $this->whenHas('followed_by_following_count'),
        ];
    }

    /**
     * @return array{platform: string, url: string, thumbnail_url: string, ends_at: string}|null
     */
    private function live(): ?array
    {
        $stream = $this->liveStream();
        $platform = $this->live_platform;
        $endsAt = $this->live_ends_at;

        return $stream instanceof LiveStream && $platform instanceof LivePlatform && $endsAt instanceof CarbonInterface ? [
            'platform' => $platform->value,
            ...$stream->toArray(),
            'ends_at' => $endsAt->toIso8601ZuluString(),
        ] : null;
    }
}
