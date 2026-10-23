<?php

declare(strict_types=1);

use App\Enums\GifProvider;

it('labels each provider as the ui does', function (): void {
    expect(GifProvider::Giphy->label())->toBe('GIPHY')
        ->and(GifProvider::Klipy->label())->toBe('KLIPY');
});

it('accepts only its own media hosts', function (GifProvider $provider, string $host, bool $allowed): void {
    $matches = array_filter(
        $provider->mediaHostPatterns(),
        fn (string $pattern): bool => preg_match($pattern, $host) === 1,
    );

    expect($matches !== [])->toBe($allowed);
})->with([
    'giphy media' => [GifProvider::Giphy, 'media2.giphy.com', true],
    'giphy api host' => [GifProvider::Giphy, 'api.giphy.com', false],
    'klipy static' => [GifProvider::Klipy, 'static.klipy.com', true],
    'klipy numbered static' => [GifProvider::Klipy, 'static2.klipy.com', true],
    'klipy api host' => [GifProvider::Klipy, 'api.klipy.com', false],
]);

it('accepts only its own page hosts', function (GifProvider $provider, string $host, bool $allowed): void {
    $matches = array_filter(
        $provider->pageHostPatterns(),
        fn (string $pattern): bool => preg_match($pattern, $host) === 1,
    );

    expect($matches !== [])->toBe($allowed);
})->with([
    'giphy page' => [GifProvider::Giphy, 'giphy.com', true],
    'giphy www page' => [GifProvider::Giphy, 'www.giphy.com', true],
    'klipy page' => [GifProvider::Klipy, 'klipy.com', true],
    'klipy www page' => [GifProvider::Klipy, 'www.klipy.com', true],
    'giphy page against klipy' => [GifProvider::Klipy, 'giphy.com', false],
]);
