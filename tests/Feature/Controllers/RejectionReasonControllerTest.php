<?php

declare(strict_types=1);

use App\Models\RejectionReason;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may create a rejection reason', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('rejection-reason.store'), [
            'title' => 'Off-topic',
            'body' => 'Commits are about what you are building.',
        ]);

    $response->assertRedirectToRoute('moderation.show');

    $reason = RejectionReason::query()->sole();

    expect($reason->title)->toBe('Off-topic')
        ->and($reason->body)->toBe('Commits are about what you are building.');
});

it('requires a valid rejection reason', function (array $data, string $error): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('rejection-reason.store'), $data);

    $response->assertRedirectToRoute('moderation.show')
        ->assertSessionHasErrors($error);

    expect(RejectionReason::query()->count())->toBe(0);
})->with([
    'missing title' => [['body' => 'Spam.'], 'title'],
    'long title' => [['title' => str_repeat('a', 41), 'body' => 'Spam.'], 'title'],
    'missing body' => [['title' => 'Spam'], 'body'],
    'long body' => [['title' => 'Spam', 'body' => str_repeat('a', 281)], 'body'],
]);

it('may update a rejection reason', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $reason = RejectionReason::factory()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->put(route('rejection-reason.update', $reason), [
            'title' => 'Spam',
            'body' => 'This looks like spam.',
        ]);

    $response->assertRedirectToRoute('moderation.show');

    expect($reason->refresh()->title)->toBe('Spam')
        ->and($reason->body)->toBe('This looks like spam.');
});

it('may delete a rejection reason', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $reason = RejectionReason::factory()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->delete(route('rejection-reason.destroy', $reason));

    $response->assertRedirectToRoute('moderation.show');

    expect(RejectionReason::query()->count())->toBe(0);
});

it('may not manage rejection reasons as a non admin', function (): void {
    $user = User::factory()->create();
    $reason = RejectionReason::factory()->create(['title' => 'Spam']);

    $this->actingAs($user)
        ->post(route('rejection-reason.store'), ['title' => 'Off-topic', 'body' => 'Off-topic.'])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('rejection-reason.update', $reason), ['title' => 'Changed', 'body' => 'Changed.'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('rejection-reason.destroy', $reason))
        ->assertForbidden();

    expect(RejectionReason::query()->sole()->title)->toBe('Spam');
});
