<?php

declare(strict_types=1);

use App\Services\Spoilers;

it('splits spoilers out of the text', function (string $text, array $segments): void {
    $spoilers = resolve(Spoilers::class);

    expect($spoilers->segments($text))->toBe($segments);
})->with([
    'no spoiler' => ['just shipped it', [['type' => 'text', 'text' => 'just shipped it']]],
    'a spoiler' => ['the butler ||did it|| again', [
        ['type' => 'text', 'text' => 'the butler '],
        ['type' => 'spoiler', 'text' => 'did it'],
        ['type' => 'text', 'text' => ' again'],
    ]],
    'only a spoiler' => ['||Snape kills Dumbledore||', [['type' => 'spoiler', 'text' => 'Snape kills Dumbledore']]],
    'spoilers across lines' => ["||first\nsecond||", [['type' => 'spoiler', 'text' => "first\nsecond"]]],
    'two spoilers' => ['||a|| and ||b||', [
        ['type' => 'spoiler', 'text' => 'a'],
        ['type' => 'text', 'text' => ' and '],
        ['type' => 'spoiler', 'text' => 'b'],
    ]],
]);

it('leaves pipes that do not wrap a spoiler as text', function (string $text): void {
    $spoilers = resolve(Spoilers::class);

    expect($spoilers->segments($text))->toBe([['type' => 'text', 'text' => $text]]);
})->with([
    'shell operators' => ['make test || make fix || exit 1'],
    'an empty spoiler' => ['nothing |||| here'],
    'a blank spoiler' => ['nothing || || here'],
    'an unclosed spoiler' => ['a ||dangling spoiler'],
]);

it('never hides code behind a spoiler', function (): void {
    $spoilers = resolve(Spoilers::class);

    expect($spoilers->segments("run `a ||b|| c` then ||this||\n```\n||not a spoiler||\n```"))->toBe([
        ['type' => 'text', 'text' => 'run '],
        ['type' => 'code', 'code' => 'a ||b|| c'],
        ['type' => 'text', 'text' => ' then '],
        ['type' => 'spoiler', 'text' => 'this'],
        ['type' => 'block', 'language' => null, 'html' => '||not a spoiler||'],
    ]);
});

it('masks spoilers outside code', function (): void {
    $spoilers = resolve(Spoilers::class);

    expect($spoilers->mask('Dale ||is the killer||, see `a ||b||`'))->toBe('Dale [spoiler], see `a ||b||`');
});
