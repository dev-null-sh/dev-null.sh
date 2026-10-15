<?php

declare(strict_types=1);

use App\Models\User;

it('may reset the rss feed link', function (): void {
    $user = User::factory()->create(['feed_token' => 'leaked-feed-token']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('feed-token.update'));

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'RSS link reset. Update it in your feed reader.',
        ]);

    expect($user->refresh()->feed_token)->not->toBeNull()->not->toBe('leaked-feed-token');

    $this->get(route('following-feed.show', 'leaked-feed-token'))->assertNotFound();
    $this->get(route('following-feed.show', $user->feed_token))->assertOk();
});

it('may create the rss feed link when there is none yet', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('feed-token.update'))
        ->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->feed_token)->toHaveLength(48);
});

it('requires authentication', function (): void {
    $response = $this->put(route('feed-token.update'));

    $response->assertRedirectToRoute('home');
});
