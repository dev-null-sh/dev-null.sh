<?php

declare(strict_types=1);

use App\Http\Middleware\HandleLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('uses the language from the locale cookie', function (): void {
    $request = Request::create('/', 'GET');
    $request->cookies->set('locale', 'hi');

    $response = new HandleLocale()->handle($request, fn (): Response => response('OK'));

    expect(app()->getLocale())->toBe('hi')
        ->and($response->getContent())->toBe('OK');
});

it('keeps english without a locale cookie', function (): void {
    new HandleLocale()->handle(Request::create('/', 'GET'), fn (): Response => response('OK'));

    expect(app()->getLocale())->toBe('en');
});

it('ignores a locale cookie for a language devnull is not translated into', function (): void {
    $request = Request::create('/', 'GET');
    $request->cookies->set('locale', 'xx');

    new HandleLocale()->handle($request, fn (): Response => response('OK'));

    expect(app()->getLocale())->toBe('en');
});

it('ignores a locale cookie that is not a string', function (): void {
    $request = Request::create('/', 'GET');
    $request->cookies->set('locale', ['hi']);

    new HandleLocale()->handle($request, fn (): Response => response('OK'));

    expect(app()->getLocale())->toBe('en');
});
