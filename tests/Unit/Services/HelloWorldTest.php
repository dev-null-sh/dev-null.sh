<?php

declare(strict_types=1);

use App\Services\HelloWorld;

it('recognizes a hello world', function (string $body): void {
    expect(resolve(HelloWorld::class)->matches($body))->toBeTrue();
})->with([
    'plain' => 'hello world',
    'caps' => 'Hello World',
    'classic' => 'Hello, World!',
    'comma' => 'hello, world',
    'padded' => '  hello world  ',
    'period' => 'hello world.',
    'spaced' => 'hello  world',
    'quoted' => '"Hello, World!"',
]);

it('ignores anything that is not only a hello world', function (string $body): void {
    expect(resolve(HelloWorld::class)->matches($body))->toBeFalse();
})->with([
    'extra words' => 'hello world, we shipped',
    'plural' => 'hello worlds',
    'short' => 'hello',
    'empty' => '',
    'no space' => 'hello,world',
]);
