<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An organization's GitHub profile, as its page on the web shows it.
 *
 * @mixin Profile
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
        ];
    }
}
