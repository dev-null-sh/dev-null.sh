<?php

declare(strict_types=1);

use App\Enums\GifProvider;
use App\Rules\GifMediaUrl;
use Illuminate\Support\Facades\Validator;

it('accepts a matching provider media url verbatim', function (GifProvider $provider, string $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifMediaUrl($provider)]]);

    expect($validator->passes())->toBeTrue();
})->with([
    'giphy' => [GifProvider::Giphy, 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/giphy-downsized-medium.gif?cid=abc123'],
    'klipy' => [GifProvider::Klipy, 'https://static2.klipy.com/ii/935d7ab9/14/af/8GCrVAB7.gif'],
]);

it('rejects urls from the other provider', function (GifProvider $provider, string $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifMediaUrl($provider)]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'giphy url for klipy' => [GifProvider::Klipy, 'https://media2.giphy.com/media/xT9IgzoKnwFNmISR8I/giphy.gif'],
    'klipy url for giphy' => [GifProvider::Giphy, 'https://static.klipy.com/ii/935d7ab9/8GCrVAB7.gif'],
]);

it('rejects unsafe or malformed media urls', function (mixed $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifMediaUrl(GifProvider::Klipy)]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'not https' => ['http://static.klipy.com/ii/x.gif'],
    'not a gif' => ['https://static.klipy.com/ii/x.webp'],
    'with port' => ['https://static.klipy.com:8443/ii/x.gif'],
    'with userinfo' => ['https://user:pass@static.klipy.com/ii/x.gif'],
    'dot segment' => ['https://static.klipy.com/ii/../x.gif'],
    'array value' => [['nope']],
]);
