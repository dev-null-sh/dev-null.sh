<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An organization's GitHub profile, as its page on the web shows it. In a list, a signed request also gets whether its
 * user follows it.
 *
 * @mixin Organization
 */
final class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'bio' => $this->bio,
            'location' => $this->location,
            'website' => $this->website,
            'github_url' => "https://github.com/{$this->handle}",
            'html_url' => route('user.show', ['user' => $this->handle]),
            'is_followed' => $this->whenHas('is_followed'),
        ];
    }
}
