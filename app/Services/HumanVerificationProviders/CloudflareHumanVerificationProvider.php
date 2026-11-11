<?php

declare(strict_types=1);

namespace App\Services\HumanVerificationProviders;

use App\Contracts\Services\HumanVerificationProvider;
use Illuminate\Support\Facades\Http;

final readonly class CloudflareHumanVerificationProvider implements HumanVerificationProvider
{
    public function siteKey(): string
    {
        return config()->string('services.cloudflare_turnstile.site_key');
    }

    public function isHuman(string $token): bool
    {
        $secretKey = config('services.cloudflare_turnstile.secret_key');
        assert(is_string($secretKey));

        return Http::asForm()
            ->timeout(5)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $token,
            ])
            ->throw()
            ->fluent()
            ->boolean('success');
    }
}
