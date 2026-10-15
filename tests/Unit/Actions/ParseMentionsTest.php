<?php

declare(strict_types=1);

use App\Actions\ParseMentions;
use App\Models\User;

it('extracts unique usernames case-insensitively', function (): void {
    $action = resolve(ParseMentions::class);

    expect($action->usernames('@JDoe hello @jdoe and @alice'))->toBe(['jdoe', 'alice']);
});

it('resolves mentions to existing users', function (): void {
    $jdoe = User::factory()->create(['username' => 'jdoe']);
    User::factory()->create(['username' => 'other']);

    $mentioned = resolve(ParseMentions::class)->handle('@JDoe hello @nobody');

    expect($mentioned)->toHaveCount(1)
        ->and($mentioned->sole()->is($jdoe))->toBeTrue();
});

it('ignores emails and invalid handles', function (string $body): void {
    User::factory()->create(['username' => 'jdoe']);

    $action = resolve(ParseMentions::class);

    expect($action->usernames($body))->toBe([])
        ->and($action->handle($body))->toBeEmpty();
})->with([
    'email' => 'ping a@b.com please',
    'trailing hyphen' => 'hey @jdoe-',
    'leading hyphen' => 'hey @-jdoe',
    'empty at' => 'just @ alone',
    'too long' => '@'.str_repeat('a', 40),
]);

it('matches handles at boundaries', function (string $body, array $expected): void {
    expect(resolve(ParseMentions::class)->usernames($body))->toBe($expected);
})->with([
    'start of string' => ['@jdoe hello', ['jdoe']],
    'after punctuation' => ['(@jdoe)', ['jdoe']],
    'hyphenated' => ['shipped with @jane-doe', ['jane-doe']],
    'dotted' => ['ping @alden.welch please', ['alden.welch']],
    'dotted then sentence period' => ['Thanks @alden.welch.', ['alden.welch']],
    'single character' => ['hi @a', ['a']],
]);

it('resolves dotted usernames', function (): void {
    $user = User::factory()->create(['username' => 'alden.welch']);

    $mentioned = resolve(ParseMentions::class)->handle('Hey @alden.welch');

    expect($mentioned->sole()->is($user))->toBeTrue();
});
