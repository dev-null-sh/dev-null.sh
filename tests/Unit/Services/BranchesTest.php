<?php

declare(strict_types=1);

use App\Services\Branches;

it('normalizes a branch field', function (?string $input, ?string $expected): void {
    expect(resolve(Branches::class)->normalize($input))->toBe($expected);
})->with([
    'plain' => ['Laravel', 'laravel'],
    'with hash' => ['#PHP', 'php'],
    'trimmed' => ['  rust  ', 'rust'],
    'blank' => ['   ', null],
    'null' => [null, null],
]);

it('finds legacy hashtags in prose, lowercased and once each', function (): void {
    $hashtags = resolve(Branches::class);

    expect($hashtags->namesInBody('#Laravel and #PHP, thanks! cc #laravel'))
        ->toBe(['laravel']);
});

it('finds hashtags next to punctuation', function (string $text, array $names): void {
    $hashtags = resolve(Branches::class);

    expect($hashtags->namesInBody($text))->toBe($names);
})->with([
    'sentence end' => ['Shipped #laravel.', ['laravel']],
    'question' => ['Ready for #php?', ['php']],
    'parentheses' => ['(see #rust)', ['rust']],
    'line start' => ["Shipped\n#go", ['go']],
    'underscore' => ['Tracking #100DaysOfCode', ['100daysofcode']],
    'colon' => ['#laravel: look', ['laravel']],
]);

it('ignores what only looks like a hashtag', function (string $text): void {
    $hashtags = resolve(Branches::class);

    expect($hashtags->namesInBody($text))->toBe([]);
})->with([
    'fragment after path' => ['See docs/path#anchor'],
    'url fragment' => ['https://example.com/#section'],
    'lone hash' => ['Meet # 5pm'],
    'double hash' => ['##laravel'],
    'leading underscore' => ['#_private'],
    'purely numeric' => ['Issue #42 is open'],
    'too long' => ['#'.str_repeat('a', 51)],
]);

it('allows tags of up to 50 characters', function (): void {
    $hashtags = resolve(Branches::class);
    $tag = str_repeat('a', 50);

    expect($hashtags->namesInBody('#'.$tag))->toBe([$tag]);
});

it('keeps at most one hashtag, first wins', function (): void {
    $hashtags = resolve(Branches::class);

    expect($hashtags->namesInBody('#one #two #three #one'))->toBe(['one']);
});

it('skips hashtags in code', function (): void {
    $hashtags = resolve(Branches::class);

    expect($hashtags->namesInBody("shipped #laravel with `#define`\n```c\n#define MAX 1\n```"))
        ->toBe(['laravel']);
});

it('validates stored tag names', function (string $tag, bool $valid): void {
    expect(resolve(Branches::class)->isValid($tag))->toBe($valid);
})->with([
    'simple' => ['laravel', true],
    'with digits' => ['100daysofcode', true],
    'with underscore' => ['hello_world', true],
    'uppercase' => ['Laravel', false],
    'with hash' => ['#laravel', false],
    'empty' => ['', false],
    'numeric only' => ['42', false],
    'too long' => [str_repeat('a', 51), false],
]);
