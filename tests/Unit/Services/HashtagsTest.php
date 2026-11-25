<?php

declare(strict_types=1);

use App\Services\Hashtags;

it('extracts only prose guild handles without deleting text that also occurs in urls', function (string $text, array $handles): void {
    expect(resolve(Hashtags::class)->handles($text))->toBe($handles);
})->with([
    'scheme host is also a hashtag' => ['#linux https://linux', ['linux']],
    'scheme host follows the url' => ['https://linux #linux', ['linux']],
    'bare domain fragment' => ['example.com/#hidden #rust', ['rust']],
    'scheme fragment' => ['https://example.com/#hidden #rust', ['rust']],
    'overlapping urls' => ['https://example.com/#hidden https://example.com/#hidden#alsohidden #rust', ['rust']],
    'repeated bare domains' => ['example.com/#hidden and example.com/#hidden #rust', ['rust']],
    'http scheme' => ['http://linux #linux', ['linux']],
    'uppercase scheme' => ['HTTPS://linux #linux', ['linux']],
    'scheme host inside a longer hashtag' => ['https://linux #alpine_linux', ['alpine_linux']],
    'bare url inside a path' => ['example.com /example.com/#rust', []],
    'punctuation' => ['(#RUST) #café #rust', ['rust', 'café']],
    'explicit colors versus hex-shaped guilds' => ['#dead color(#dead) #ff8a4c #000', ['dead', 'ff8a4c']],
    'inline code' => ['`#hidden` #rust', ['rust']],
    'fenced code' => ["```\n#hidden\n```\n#rust", ['rust']],
    'quote marker' => ['>#rust', ['rust']],
    'quote on another line' => ["text\n>#rust", ['rust']],
    'spaced quote' => ['> #rust', ['rust']],
    'nested angle bracket' => ['>>#rust', []],
    'inline angle bracket' => ['text>#rust', []],
    'code followed by angle bracket' => ['`x`>#rust', []],
]);
