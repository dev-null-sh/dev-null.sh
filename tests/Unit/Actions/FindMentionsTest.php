<?php

declare(strict_types=1);

use App\Actions\FindMentions;

it('finds mentions, lowercased and once each, in order', function (): void {
    $action = resolve(FindMentions::class);

    expect($action->handle('@NunoMaduro and @taylorotwell, thanks! cc @nunomaduro'))
        ->toBe(['nunomaduro', 'taylorotwell']);
});

it('finds mentions next to punctuation', function (string $text, array $usernames): void {
    $action = resolve(FindMentions::class);

    expect($action->handle($text))->toBe($usernames);
})->with([
    'sentence end' => ['Thanks @nuno.', ['nuno']],
    'question' => ['Right, @nuno?', ['nuno']],
    'parentheses' => ['(with @nuno)', ['nuno']],
    'line start' => ["Shipped\n@nuno", ['nuno']],
    'hyphenated' => ['Hi @jess-archer', ['jess-archer']],
    'colon' => ['@nuno: look', ['nuno']],
]);

it('ignores what only looks like a mention', function (string $text): void {
    $action = resolve(FindMentions::class);

    expect($action->handle($text))->toBe([]);
})->with([
    'email' => ['Mail me at nuno@laravel.com'],
    'scoped package' => ['npm i @inertiajs/react'],
    'domain' => ['Try @vue.js'],
    'path' => ['See medium.com/@nuno'],
    'lone at' => ['Meet @ 5pm'],
    'leading hyphen' => ['@-nuno'],
    'trailing hyphen' => ['@nuno-'],
    'double at' => ['@@nuno'],
    'too long' => ['@'.str_repeat('a', 40)],
]);

it('allows usernames of up to 39 characters', function (): void {
    $action = resolve(FindMentions::class);

    expect($action->handle('@'.str_repeat('a', 39)))->toBe([str_repeat('a', 39)]);
});
