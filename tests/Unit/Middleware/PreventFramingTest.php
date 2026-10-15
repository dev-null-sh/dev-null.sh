<?php

declare(strict_types=1);

use App\Http\Middleware\PreventFraming;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('prevents the response from being framed', function (): void {
    $middleware = new PreventFraming();

    $response = $middleware->handle(Request::create('/', 'GET'), fn ($req): Response => response('OK'));

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'none'")
        ->and($response->getContent())->toBe('OK');
});
