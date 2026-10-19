<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use App\Services\TagDnsVerification;
use Illuminate\Validation\ValidationException;

/**
 * Queues a branch for moderator review once its TXT record is present.
 */
final readonly class RequestTagVerification
{
    public function __construct(private TagDnsVerification $verification)
    {
        //
    }

    public function handle(Tag $tag): Tag
    {
        if ($tag->isVerified()) {
            return $tag;
        }

        if ($tag->domain === null || $tag->verification_token === null) {
            throw ValidationException::withMessages(['domain' => 'Claim a domain for this branch first.']);
        }

        if (! $this->verification->matches($tag)) {
            throw ValidationException::withMessages([
                'domain' => 'No matching TXT record at '.$this->verification->hostFor($tag->domain).'.',
            ]);
        }

        if ($tag->verification_requested_at === null) {
            $tag->forceFill(['verification_requested_at' => now()])->save();
        }

        return $tag->refresh();
    }
}
