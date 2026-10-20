<?php

declare(strict_types=1);

use App\Services\EmbedProviders\Giphy;
use Uri\WhatWg\Url;

it('builds external GIF and static poster URLs without an API', function (string $url): void {
    expect(resolve(Giphy::class)->embed(Url::parse($url))?->toArray())->toBe([
        'provider' => 'GIPHY',
        'kind' => 'gif',
        'src' => 'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy.gif',
        'aspect_ratio' => '1 / 1',
        'height' => null,
        'poster' => 'https://media.giphy.com/media/JIX9t2j0ZTN9S/giphy_s.gif',
    ]);
})->with([
    'share' => 'https://giphy.com/gifs/cat-typing-JIX9t2j0ZTN9S',
    'id' => 'https://www.giphy.com/gifs/JIX9t2j0ZTN9S/',
    'embed' => 'https://giphy.com/embed/JIX9t2j0ZTN9S',
    'uppercase section' => 'https://giphy.com/GIFS/cat-JIX9t2j0ZTN9S',
    'query and fragment' => 'https://giphy.com/gifs/cat-JIX9t2j0ZTN9S?tracking=ignored#ignored',
    'http link' => 'http://giphy.com/gifs/JIX9t2j0ZTN9S',
]);

it('does not embed malformed links or untrusted hosts', function (string $url): void {
    expect(resolve(Giphy::class)->embed(Url::parse($url)))->toBeNull();
})->with([
    'home' => 'https://giphy.com/',
    'search' => 'https://giphy.com/search/cats',
    'missing id' => 'https://giphy.com/gifs/',
    'empty slug identifier' => 'https://giphy.com/gifs/cat-',
    'embed with slug' => 'https://giphy.com/embed/cat-JIX9t2j0ZTN9S',
    'escaped id' => 'https://giphy.com/gifs/cat-%3Cscript%3E',
    'extra segment' => 'https://giphy.com/embed/JIX9t2j0ZTN9S/other',
    'lookalike' => 'https://giphy.com.example.com/gifs/JIX9t2j0ZTN9S',
    'other host' => 'https://example.com/gifs/JIX9t2j0ZTN9S',
    'credentials' => 'https://user:secret@giphy.com/gifs/JIX9t2j0ZTN9S',
    'username' => 'https://user@giphy.com/gifs/JIX9t2j0ZTN9S',
    'password' => 'https://:secret@giphy.com/gifs/JIX9t2j0ZTN9S',
    'port' => 'https://giphy.com:8443/gifs/JIX9t2j0ZTN9S',
    'scheme' => 'ftp://giphy.com/gifs/JIX9t2j0ZTN9S',
]);
