<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('_test/ip', fn (Request $request): string => (string) $request->ip());
});

it('believes the forwarded address of a proxy it trusts', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.0/8']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('_test/ip')
        ->assertSee('203.0.113.9');
});

it('ignores a forwarded address from anywhere else', function (): void {
    config(['trustedproxy.proxies' => []]);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('_test/ip')
        ->assertSee('198.51.100.7');
});

it('ignores a forwarded address on a host the framework would otherwise trust', function (): void {
    // Given null instead of an empty list, the framework trusts whatever connected on these hosts, and a caller
    // reaching the app directly picks its own address.
    config(['trustedproxy.proxies' => []]);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
        ->get('http://app.on-forge.com/_test/ip')
        ->assertSee('198.51.100.7');
});
