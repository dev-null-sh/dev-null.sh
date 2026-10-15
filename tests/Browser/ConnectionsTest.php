<?php

declare(strict_types=1);

use App\Models\User;

it('may follow and unfollow people from a followers list', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create(['username' => 'octocat']);
    $follower = User::factory()->create(['username' => 'hubot']);
    $user->followers()->attach([$follower->id, $viewer->id]);

    $this->actingAs($viewer);

    $page = visit(route('user-followers.index', $user))
        ->assertSee('@hubot')
        ->assertCount('[data-test="follow-button"]', 1)
        ->click('[aria-label="Follow @hubot"]')
        ->assertPresent('[aria-label="Unfollow @hubot"]')
        ->assertNoJavaScriptErrors();

    expect($viewer->isFollowing($follower))->toBeTrue();

    $page->click('[aria-label="Unfollow @hubot"]')
        ->assertPresent('[aria-label="Follow @hubot"]')
        ->assertNoJavaScriptErrors();

    expect($viewer->isFollowing($follower))->toBeFalse();
});

it('may switch between followers and following', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $user->followers()->attach(User::factory()->create(['username' => 'hubot']));
    $user->following()->attach(User::factory()->create(['username' => 'mona']));

    $this->actingAs($user);

    visit(route('user-followers.index', $user))
        ->assertSee('@hubot')
        ->assertDontSee('@mona')
        ->click('Following')
        ->assertPathIs('/@octocat/following')
        ->assertSee('@mona')
        ->assertDontSee('@hubot')
        ->click('Followers')
        ->assertPathIs('/@octocat/followers')
        ->assertSee('@hubot')
        ->assertNoJavaScriptErrors();
});
