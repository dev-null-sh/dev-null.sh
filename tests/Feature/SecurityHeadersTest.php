<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('_test/headers', fn (): string => 'ok');
});

it('sends the headers a browser reads before it trusts the page', function (): void {
    $this->get('_test/headers')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(), payment=(), usb=()')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});

it('pins HTTPS only when the request came over it', function (): void {
    $this->get('https://localhost/_test/headers')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');

    $this->get('http://localhost/_test/headers')
        ->assertHeaderMissing('Strict-Transport-Security');
});
