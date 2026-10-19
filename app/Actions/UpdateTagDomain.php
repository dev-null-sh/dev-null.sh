<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use App\Services\TagDnsVerification;
use Illuminate\Validation\ValidationException;

final readonly class UpdateTagDomain
{
    public function __construct(private TagDnsVerification $verification)
    {
        //
    }

    public function handle(Tag $tag, string $domain): Tag
    {
        $domain = $this->verification->normalizeDomain($domain);

        if (! $this->verification->isValidDomain($domain)) {
            throw ValidationException::withMessages(['domain' => 'Enter a valid domain, like laravel.com.']);
        }

        $tag->forceFill([
            'domain' => $domain,
            'verification_token' => $this->verification->newToken(),
            'verification_requested_at' => null,
            'verified_at' => null,
            'verified_domain' => null,
            'domain_description' => null,
            'og_image_url' => null,
            'brand_color' => null,
        ])->save();

        return $tag->refresh();
    }
}
