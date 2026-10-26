<?php

declare(strict_types=1);

use App\Models\User;

it('shows the signed-in user their saved commit visibility settings', function (): void {
    $user = User::factory()->create(['hidden_commit_channels' => ['cli', 'ai']]);

    $this->actingAs($user)->get(route('user-commit-visibility.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('commit-visibility/edit')
            ->where('hiddenCommitChannels', ['cli', 'ai']));
});

it('shows all channels by default', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('user-commit-visibility.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hiddenCommitChannels', []));
});

it('saves the choices only for the signed-in user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['hidden_commit_channels' => ['ai']]);

    $this->actingAs($user)->fromRoute('user-commit-visibility.edit')
        ->put(route('user-commit-visibility.update'), [
            'hidden_commit_channels' => ['cli', 'ci'],
            'user_id' => $other->id,
        ])
        ->assertRedirectToRoute('user-commit-visibility.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Commit visibility saved.']);

    expect($user->refresh()->hidden_commit_channels)->toBe(['cli', 'ci']);
    expect($other->refresh()->hidden_commit_channels)->toBe(['ai']);
    expect($user->toArray())->not->toHaveKey('hidden_commit_channels');
});

it('can show every channel again', function (): void {
    $user = User::factory()->create(['hidden_commit_channels' => ['cli', 'ci', 'ai']]);

    $this->actingAs($user)->put(route('user-commit-visibility.update'), ['hidden_commit_channels' => []])
        ->assertRedirect();

    expect($user->refresh()->hidden_commit_channels)->toBe([]);
});

it('rejects invalid visibility choices without changing the setting', function (mixed $channels): void {
    $user = User::factory()->create(['hidden_commit_channels' => ['ci']]);

    $this->actingAs($user)->fromRoute('user-commit-visibility.edit')
        ->put(route('user-commit-visibility.update'), ['hidden_commit_channels' => $channels])
        ->assertRedirectToRoute('user-commit-visibility.edit')
        ->assertSessionHasErrors();

    expect($user->refresh()->hidden_commit_channels)->toBe(['ci']);
})->with([
    'null' => [null],
    'string' => ['cli'],
    'bot' => [['bot']],
    'unknown channel' => [['web']],
    'duplicate' => [['cli', 'cli']],
    'non-string' => [[1]],
    'nested' => [[['cli']]],
    'associative' => [['source' => 'cli']],
    'too many' => [['cli', 'ci', 'ai', 'cli']],
]);

it('requires an explicit visibility choice', function (): void {
    $user = User::factory()->create(['hidden_commit_channels' => ['ai']]);

    $this->actingAs($user)->put(route('user-commit-visibility.update'))
        ->assertSessionHasErrors('hidden_commit_channels');

    expect($user->refresh()->hidden_commit_channels)->toBe(['ai']);
});

it('requires authentication to view or change visibility settings', function (): void {
    $this->get(route('user-commit-visibility.edit'))->assertRedirectToRoute('home');
    $this->put(route('user-commit-visibility.update'), ['hidden_commit_channels' => ['ai']])
        ->assertRedirectToRoute('home');
});
