<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tag;
use App\Services\TagDnsVerification;
use Illuminate\Validation\ValidationException;

/**
 * Confirms a pending verification after a moderator re-checks the TXT record.
 */
final readonly class ApproveTagVerification
{
    public function __construct(
        private TagDnsVerification $verification,
        private RefreshTagSiteMeta $refreshSiteMeta,
    ) {
        //
    }

    public function handle(Tag $tag): Tag
    {
        if ($tag->verification_requested_at === null) {
            throw ValidationException::withMessages(['tag' => 'This branch is not waiting for verification.']);
        }

        if ($tag->domain === null || $tag->verification_token === null) {
            throw ValidationException::withMessages(['domain' => 'This branch has no domain to verify.']);
        }

        if (! $this->verification->matches($tag)) {
            throw ValidationException::withMessages([
                'domain' => 'No matching TXT record at '.$this->verification->hostFor($tag->domain).'.',
            ]);
        }

        $tag->forceFill([
            'verified_at' => now(),
            'verified_domain' => $tag->domain,
            'verification_requested_at' => null,
        ])->save();

        return $this->refreshSiteMeta->handle($tag->refresh());
    }
}
