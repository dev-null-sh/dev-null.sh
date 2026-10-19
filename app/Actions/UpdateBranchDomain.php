<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Branch;
use App\Services\BranchDnsVerification;
use Illuminate\Validation\ValidationException;

final readonly class UpdateBranchDomain
{
    public function __construct(private BranchDnsVerification $verification)
    {
        //
    }

    public function handle(Branch $branch, string $domain): Branch
    {
        $domain = $this->verification->normalizeDomain($domain);

        if (! $this->verification->isValidDomain($domain)) {
            throw ValidationException::withMessages(['domain' => 'Enter a valid domain, like laravel.com.']);
        }

        $branch->forceFill([
            'domain' => $domain,
            'verification_token' => $this->verification->newToken(),
            'verification_requested_at' => null,
            'txt_matched_at' => null,
            'verified_at' => null,
            'verified_domain' => null,
            'domain_description' => null,
            'og_image_url' => null,
            'brand_color' => null,
        ])->save();

        return $branch->refresh();
    }
}
