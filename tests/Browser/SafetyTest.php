<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('mutes someone from their profile and unmutes them from settings', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create(['username' => 'octocat']);
    Commit::factory()->for($user)->create(['body' => 'Still on my own profile.']);
    $this->actingAs($viewer);

    visit(route('user.show', $user))
        ->click('[aria-label="More options for @octocat"]')
        ->click('Mute @octocat')
        ->assertSee('Muted @octocat.')
        ->assertSee('Still on my own profile.')
        ->assertNoJavaScriptErrors();

    expect($viewer->isMuting($user))->toBeTrue();

    visit(route('safety.edit'))
        ->assertSee('@octocat')
        ->click('[aria-label="Unmute @octocat"]')
        ->assertSee("You haven't muted anyone.")
        ->assertNoJavaScriptErrors();

    expect($viewer->isMuting($user))->toBeFalse();
});

it('blocks someone after confirming and shows their profile as blocked', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create(['username' => 'octocat', 'name' => 'The Octocat']);
    Commit::factory()->for($user)->create(['body' => 'Hidden once blocked.']);
    $viewer->following()->attach($user);
    $this->actingAs($viewer);

    visit(route('user.show', $user))
        ->click('[aria-label="More options for @octocat"]')
        ->click('Block @octocat')
        ->assertSee('Block @octocat?')
        ->click('[data-test="confirm-block-button"]')
        ->assertSee('You blocked The Octocat.')
        ->assertDontSee('Hidden once blocked.')
        ->click('[data-test="unblock-button"]')
        ->assertSee('Hidden once blocked.')
        ->assertNoJavaScriptErrors();

    expect($viewer->isBlocking($user))->toBeFalse()
        ->and($viewer->isFollowing($user))->toBeFalse();
});
