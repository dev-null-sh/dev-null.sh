<?php

declare(strict_types=1);

use App\Actions\CreatePost;
use App\Models\Topic;
use App\Models\User;

it('may create a post', function (): void {
    $user = User::factory()->create();

    $action = resolve(CreatePost::class);

    $post = $action->handle($user, 'Hello, devnull!');

    expect($post->exists)->toBeTrue()
        ->and($post->body)->toBe('Hello, devnull!')
        ->and($post->user->is($user))->toBeTrue();
});

it('groups a post into topics from its hashtags', function (): void {
    $user = User::factory()->create();
    $existing = Topic::factory()->create(['name' => 'laravel']);

    $post = resolve(CreatePost::class)->handle($user, '#Laravel and #php_8 tips, #laravel again');

    expect($post->topics->pluck('name')->sort()->values()->all())->toBe(['laravel', 'php_8'])
        ->and($post->topics->firstWhere('name', 'laravel')->is($existing))->toBeTrue()
        ->and(Topic::query()->count())->toBe(2);
});

it('ignores text that is not a hashtag', function (string $body): void {
    $post = resolve(CreatePost::class)->handle(User::factory()->create(), $body);

    expect($post->topics)->toBeEmpty();
})->with([
    'no hashtag' => 'Hello, devnull!',
    'only digits' => 'Issue #123',
    'glued to a word' => 'I write C# and a#b',
    'too long' => '#'.str_repeat('a', 51),
]);
