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

it('finds legacy branches in prose, lowercased and once each', function (): void {
    $branches = resolve(Branches::class);

    expect($branches->namesInBody('#Laravel and #PHP, thanks! cc #laravel'))
        ->toBe(['laravel']);
});

it('finds branches next to punctuation', function (string $text, array $names): void {
    $branches = resolve(Branches::class);

    expect($branches->namesInBody($text))->toBe($names);
})->with([
    'sentence end' => ['Shipped #laravel.', ['laravel']],
    'question' => ['Ready for #php?', ['php']],
    'parentheses' => ['(see #rust)', ['rust']],
    'line start' => ["Shipped\n#go", ['go']],
    'underscore' => ['Tracking #100DaysOfCode', ['100daysofcode']],
    'colon' => ['#laravel: look', ['laravel']],
]);

it('ignores what only looks like a branch', function (string $text): void {
    $branches = resolve(Branches::class);

    expect($branches->namesInBody($text))->toBe([]);
})->with([
    'fragment after path' => ['See docs/path#anchor'],
    'url fragment' => ['https://example.com/#section'],
    'lone hash' => ['Meet # 5pm'],
    'double hash' => ['##laravel'],
    'leading underscore' => ['#_private'],
    'purely numeric' => ['Issue #42 is open'],
    'too long' => ['#'.str_repeat('a', 51)],
]);

it('allows branches of up to 50 characters', function (): void {
    $branches = resolve(Branches::class);
    $branch = str_repeat('a', 50);

    expect($branches->namesInBody('#'.$branch))->toBe([$branch]);
});

it('keeps at most one branch, first wins', function (): void {
    $branches = resolve(Branches::class);

    expect($branches->namesInBody('#one #two #three #one'))->toBe(['one']);
});

it('skips branches in code', function (): void {
    $branches = resolve(Branches::class);

    expect($branches->namesInBody("shipped #laravel with `#define`\n```c\n#define MAX 1\n```"))
        ->toBe(['laravel']);
});

it('validates stored branch names', function (string $branch, bool $valid): void {
    expect(resolve(Branches::class)->isValid($branch))->toBe($valid);
})->with([
    'simple' => ['laravel', true],
    'with digits' => ['100daysofcode', true],
    'with underscore' => ['hello_world', true],
    'with hyphen' => ['another-random-thing', true],
    'uppercase' => ['Laravel', false],
    'with hash' => ['#laravel', false],
    'empty' => ['', false],
    'numeric only' => ['42', false],
    'leading hyphen' => ['-laravel', false],
    'too long' => [str_repeat('a', 51), false],
]);
