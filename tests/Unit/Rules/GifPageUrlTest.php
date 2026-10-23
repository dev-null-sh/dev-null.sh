<?php

declare(strict_types=1);

use App\Enums\GifProvider;
use App\Rules\GifPageUrl;
use Illuminate\Support\Facades\Validator;

it('accepts matching provider page urls', function (GifProvider $provider, string $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifPageUrl($provider)]]);

    expect($validator->passes())->toBeTrue();
})->with([
    'giphy' => [GifProvider::Giphy, 'https://giphy.com/gifs/deal-with-it-xT9IgzoKnwFNmISR8I'],
    'klipy' => [GifProvider::Klipy, 'https://klipy.com/gifs/hello-hi-662'],
]);

it('rejects urls from the other provider', function (GifProvider $provider, string $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifPageUrl($provider)]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'giphy page for klipy' => [GifProvider::Klipy, 'https://giphy.com/gifs/x'],
    'klipy page for giphy' => [GifProvider::Giphy, 'https://klipy.com/gifs/x'],
]);

it('rejects unsafe or malformed page urls', function (mixed $url): void {
    $validator = Validator::make(['url' => $url], ['url' => [new GifPageUrl(GifProvider::Klipy)]]);

    expect($validator->fails())->toBeTrue();
})->with([
    'not https' => ['http://klipy.com/gifs/x'],
    'with port' => ['https://klipy.com:8443/gifs/x'],
    'with userinfo' => ['https://user:pass@klipy.com/gifs/x'],
    'array value' => [['nope']],
]);
