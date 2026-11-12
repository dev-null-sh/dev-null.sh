<?php

declare(strict_types=1);

use App\Enums\GifProvider;
use App\Rules\GifUrl;
use Illuminate\Support\Facades\Validator;

it('accepts a matching provider media url verbatim', function (GifProvider $provider, string $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifUrl($provider)]]);

    expect($validator->passes())->toBeTrue();
})->with([
    'giphy' => [GifProvider::Giphy, 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/giphy-downsized-medium.gif?cid=abc123'],
    'klipy' => [GifProvider::Klipy, 'https://static2.klipy.com/ii/935d7ab9/14/af/8GCrVAB7.gif'],
]);

it('rejects urls from the other provider', function (GifProvider $provider, string $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifUrl($provider)]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'giphy url for klipy' => [GifProvider::Klipy, 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/giphy.gif'],
    'klipy url for giphy' => [GifProvider::Giphy, 'https://static.klipy.com/ii/935d7ab9/8GCrVAB7.gif'],
]);

it('rejects unsafe or malformed media urls', function (mixed $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifUrl(GifProvider::Klipy)]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'not https' => ['http://static.klipy.com/ii/x.gif'],
    'not a gif' => ['https://static.klipy.com/ii/x.webp'],
    'with port' => ['https://static.klipy.com:8443/ii/x.gif'],
    'with userinfo' => ['https://user:pass@static.klipy.com/ii/x.gif'],
    'dot segment' => ['https://static.klipy.com/ii/../x.gif'],
    'array value' => [['nope']],
]);

it('accepts matching provider page urls', function (GifProvider $provider, string $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifUrl($provider, page: true)]]);

    expect($validator->passes())->toBeTrue();
})->with([
    'giphy' => [GifProvider::Giphy, 'https://giphy.com/gifs/deal-with-it-xT9IgzoKnwFNmISR8I'],
    'klipy' => [GifProvider::Klipy, 'https://klipy.com/gifs/hello-hi-662'],
]);

it('rejects page urls from the other provider', function (GifProvider $provider, string $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifUrl($provider, page: true)]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'giphy page for klipy' => [GifProvider::Klipy, 'https://giphy.com/gifs/x'],
    'klipy page for giphy' => [GifProvider::Giphy, 'https://klipy.com/gifs/x'],
]);

it('rejects unsafe or malformed page urls', function (mixed $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifUrl(GifProvider::Klipy, page: true)]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'not https' => ['http://klipy.com/gifs/x'],
    'with port' => ['https://klipy.com:8443/gifs/x'],
    'with userinfo' => ['https://user:pass@klipy.com/gifs/x'],
    'array value' => [['nope']],
]);

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
