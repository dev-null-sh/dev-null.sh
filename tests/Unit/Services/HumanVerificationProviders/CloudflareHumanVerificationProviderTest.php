<?php

declare(strict_types=1);

use App\Services\HumanVerificationProviders\CloudflareHumanVerificationProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.cloudflare_turnstile.site_key' => 'site-key', 'services.cloudflare_turnstile.secret_key' => 'secret-key']);
});

it('gives the site key the widget needs', function (): void {
    expect(new CloudflareHumanVerificationProvider()->siteKey())->toBe('site-key');
});

it('asks Turnstile about the token, and sends no address of the person', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    expect(new CloudflareHumanVerificationProvider()->isHuman('turnstile-token'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $request->isForm()
        && $request->data() === ['secret' => 'secret-key', 'response' => 'turnstile-token']);
});

it('takes a token that Turnstile refuses as not human', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);

    expect(new CloudflareHumanVerificationProvider()->isHuman('used-token'))->toBeFalse();
});

it('fails when Turnstile cannot answer', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response([], 500)]);

    new CloudflareHumanVerificationProvider()->isHuman('turnstile-token');
})->throws(RequestException::class);
