<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

it('may create a commit', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => 'Hello, devnull!',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($user->commits()->sole()->body)->toBe('Hello, devnull!');
});

it('flashes the created commit', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $commit = $user->commits()->sole();

    $response->assertInertiaFlash('committed', [
        'id' => $commit->id,
        'body' => 'Initial commit',
    ]);
});

it('requires a body', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => '   ',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('body');

    expect(Commit::query()->count())->toBe(0);
});

it('requires the body to be at least 3 characters', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => '  ab  ',
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('body');

    expect(Commit::query()->count())->toBe(0);
});

it('requires the body to be at most 280 characters', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), [
            'body' => str_repeat('a', 281),
        ]);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertSessionHasErrors('body');

    expect(Commit::query()->count())->toBe(0);
});

it('rate limits commit creation', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)
            ->fromRoute('log.show', Log::Following)
            ->post(route('commit.store'), ['body' => "Commit {$i}"])
            ->assertRedirectToRoute('log.show', Log::Following);
    }

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'One too many']);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Slow down: 10 commits a minute. Even CI takes breaks.',
        ]);

    expect($user->commits()->count())->toBe(10);
});

it('requires authentication to create a commit', function (): void {
    $response = $this->post(route('commit.store'), [
        'body' => 'Initial commit',
    ]);

    $response->assertRedirectToRoute('home');

    expect(Commit::query()->count())->toBe(0);
});

it('may delete own commit', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs($commit->user)
        ->fromRoute('log.show', Log::Following)
        ->delete(route('commit.destroy', $commit));

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($commit->fresh())->toBeNull();
});

it('may not delete commits of other users', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->fromRoute('log.show', Log::Following)
        ->delete(route('commit.destroy', $commit));

    $response->assertForbidden();

    expect($commit->fresh())->not->toBeNull();
});

it('tells users their commit is pending review', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlash('toast.type', 'info');

    expect($user->commits()->sole()->status)->toBe(CommitStatus::Pending);
});

it('publishes commits of admins right away', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $user = User::factory()->create(['username' => 'nunomaduro']);

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlashMissing('toast');

    expect($user->commits()->sole()->status)->toBe(CommitStatus::Approved);
});
