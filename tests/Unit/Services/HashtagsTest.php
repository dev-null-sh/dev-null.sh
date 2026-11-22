<?php

declare(strict_types=1);

use App\Services\Hashtags;

it('finds hashtags that start with numbers when a letter follows them', function (string $text, array $handles): void {
    expect(resolve(Hashtags::class)->handles($text))->toBe($handles);
})->with([
    'letters first' => ['Hiring #JoBs', ['jobs']],
    'numbers first' => ['New #3DPrinting setup', ['3dprinting']],
    'numbers then a word' => ['#100DaysOfCode, day 3', ['100daysofcode']],
    'not hex' => ['#1st #2nd', ['1st', '2nd']],
    'hex then a word' => ['#3d3d3d_x', ['3d3d3d_x']],
]);

it('leaves issue references and hex colors that start with numbers alone', function (string $text): void {
    expect(resolve(Hashtags::class)->handles($text))->toBe([]);
})->with([
    'issue' => ['Fixed in #123'],
    'short color' => ['Use #0ff or #2FA'],
    'four digit color' => ['Use #0ffc'],
    'long color' => ['Use #3d3d3d.'],
    'eight digit color' => ['Use #1a2b3c4d'],
    'color before underscores' => ['Use #3d3d3d_ or #2fa__'],
    'underscore' => ['#3_d'],
]);
