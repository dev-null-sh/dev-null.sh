<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('restores a reply in the profile replies tab with its inline star and parent context', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($user)->for($parent, 'parent')->create(['body' => 'A reply worth keeping.']);
    $reply->stars()->attach($user);

    $this->actingAs($user);

    visit(route('user.show', ['user' => $user, 'tab' => 'replies']))
        ->assertSee('A reply worth keeping.')
        ->click('[data-test="delete-commit-button"]')
        ->click('[data-test="confirm-delete-commit-button"]')
        ->assertSee('Commit deleted.')
        ->assertDontSee('A reply worth keeping.')
        ->click('[data-test="commit-undo"] button')
        ->assertSee('Commit restored.')
        ->assertSee('A reply worth keeping.')
        ->assertSee('replying to @'.$parent->user->username)
        ->assertSeeIn('[data-test="star-count"]', '1')
        ->assertQueryStringHas('tab', 'replies')
        ->assertNoJavaScriptErrors();

    $this->assertNotSoftDeleted($reply);
});

it('keeps an undone commit out of the other profile tab after navigation', function (bool $isReply): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    $commit = Commit::factory()->for($user)->create([
        'parent_id' => $isReply ? $parent->id : null,
        'body' => 'Restore this in its own feed.',
    ]);
    $tab = $isReply ? 'replies' : 'commits';
    $otherTab = $isReply ? 'Commits' : 'Replies';

    $this->actingAs($user);

    visit(route('user.show', ['user' => $user, 'tab' => $tab]))
        ->click('[data-test="delete-commit-button"]')
        ->click('[data-test="confirm-delete-commit-button"]')
        ->assertSee('Commit deleted.')
        ->click('nav[aria-label="Profile feed"] a:has-text("'.$otherTab.'")')
        ->click('[data-test="commit-undo"] button')
        ->assertSee('Commit restored.')
        ->assertDontSee('Restore this in its own feed.')
        ->click('nav[aria-label="Profile feed"] a:has-text("'.ucfirst($tab).'")')
        ->assertSee('Restore this in its own feed.')
        ->assertNoJavaScriptErrors();

    $this->assertNotSoftDeleted($commit);
})->with([false, true]);
