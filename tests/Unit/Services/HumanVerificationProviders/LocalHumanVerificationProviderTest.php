<?php

declare(strict_types=1);

use App\Contracts\Services\HumanVerificationProvider;
use App\Services\HumanVerificationProviders\LocalHumanVerificationProvider;

it('is the human verification provider in testing', function (): void {
    expect(resolve(HumanVerificationProvider::class))->toBeInstanceOf(LocalHumanVerificationProvider::class);
});

it('gives the site key that Cloudflare always passes, with no account to configure', function (): void {
    expect(new LocalHumanVerificationProvider()->siteKey())->toBe('1x00000000000000000000AA');
});

it('takes any token the widget gave as human, and no token as not human', function (): void {
    $provider = new LocalHumanVerificationProvider();

    expect($provider->isHuman('turnstile-token'))->toBeTrue()
        ->and($provider->isHuman(''))->toBeFalse();
});
