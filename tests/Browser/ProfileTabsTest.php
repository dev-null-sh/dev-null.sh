<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

it('pins replaces and unpins a single card only in the commits tab', function (): void {
    $user = User::factory()->create();
    $first = Commit::factory()->for($user)->create(['body' => 'Keep this older commit in view.', 'created_at' => now()->subDay()]);
    $replacement = Commit::factory()->for($user)->create(['body' => 'Replace the highlighted commit.']);
    $parent = Commit::factory()->create();
    Commit::factory()->for($user)->for($parent, 'parent')->create(['body' => 'A reply belongs in the replies tab.']);
    $this->actingAs($user);

    visit(route('user.show', $user))
        ->click('[data-commit="'.$first->id.'"] button[aria-label="Pin to profile"]')
        ->assertCount('section[aria-label="Pinned commit"]', 1)
        ->assertCount('[data-commit="'.$first->id.'"]', 1)
        ->assertVisible('section[aria-label="Pinned commit"] [data-test="star-commit-button"]')
        ->assertVisible('section[aria-label="Pinned commit"] button[aria-label="Save commit"]')
        ->click('nav[aria-label="Profile feed"] a:has-text("Replies")')
        ->assertSee('A reply belongs in the replies tab.')
        ->assertCount('section[aria-label="Pinned commit"]', 0)
        ->assertCount('button[aria-label="Pin to profile"]', 0)
        ->assertDontSee('Keep this older commit in view.')
        ->click('nav[aria-label="Profile feed"] a:has-text("Commits")')
        ->assertCount('[data-commit="'.$first->id.'"]', 1)
        ->click('[data-commit="'.$replacement->id.'"] button[aria-label="Pin to profile"]')
        ->assertVisible('section[aria-label="Pinned commit"] [data-commit="'.$replacement->id.'"]')
        ->assertCount('[data-commit="'.$first->id.'"]', 1)
        ->assertCount('[data-commit="'.$replacement->id.'"]', 1)
        ->click('button[aria-label="Unpin from profile"]')
        ->assertCount('section[aria-label="Pinned commit"]', 0)
        ->assertCount('[data-commit="'.$first->id.'"]', 1)
        ->assertCount('[data-commit="'.$replacement->id.'"]', 1)
        ->assertNoJavaScriptErrors();
});

it('switches profile feeds without mixing commits and replies', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $parent = Commit::factory()->for(User::factory()->state(['username' => 'hubot']))->create();
    Commit::factory()->for($user)->create(['body' => 'A root commit on the profile.']);
    Commit::factory()->for($user)->for($parent, 'parent')->create(['body' => 'A reply in another conversation.']);

    $this->actingAs($user);

    visit(route('user.show', $user))
        ->assertSee('longest streak 1 day')
        ->assertSee('current 1')
        ->assertSee('A root commit on the profile.')
        ->assertDontSee('A reply in another conversation.')
        ->click('nav[aria-label="Profile feed"] a:has-text("Replies")')
        ->assertQueryStringHas('tab', 'replies')
        ->assertSee('longest streak 1 day')
        ->assertSee('current 1')
        ->assertSee('A reply in another conversation.')
        ->assertSee('replying to @hubot')
        ->assertDontSee('A root commit on the profile.')
        ->click('nav[aria-label="Profile feed"] a:has-text("Commits")')
        ->assertQueryStringMissing('tab')
        ->assertSee('longest streak 1 day')
        ->assertSee('current 1')
        ->assertSee('A root commit on the profile.')
        ->assertDontSee('A reply in another conversation.')
        ->assertNoJavaScriptErrors();
});

it('resets each feeds pagination when switching tabs', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    Commit::factory()->for($user)->count(22)->sequence(fn (Sequence $sequence): array => [
        'body' => "Root commit {$sequence->index}.",
        'created_at' => now()->subMinutes($sequence->index),
    ])->create();
    Commit::factory()->for($user)->for($parent, 'parent')->count(23)->sequence(fn (Sequence $sequence): array => [
        'body' => "Reply number {$sequence->index}.",
        'created_at' => now()->subMinutes($sequence->index),
    ])->create();

    $this->actingAs($user);

    $page = visit(route('user.show', $user))->assertSee('Root commit 0.');
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->assertSee('Root commit 21.')
        ->click('nav[aria-label="Profile feed"] a:has-text("Replies")')
        ->assertQueryStringHas('tab', 'replies')
        ->assertQueryStringMissing('cursor')
        ->assertSee('Reply number 0.')
        ->assertDontSee('Root commit 0.');

    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->assertSee('Reply number 22.')
        ->click('nav[aria-label="Profile feed"] a:has-text("Commits")')
        ->assertQueryStringMissing('tab')
        ->assertQueryStringMissing('cursor')
        ->assertSee('Root commit 0.')
        ->assertDontSee('Reply number 0.')
        ->assertNoJavaScriptErrors();
});

it('shows a useful empty state for a profile without replies', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit(route('user.show', ['user' => $user, 'tab' => 'replies']))
        ->assertSee('fatal: no replies yet')
        ->assertSee('Your replies show up here.')
        ->assertNoJavaScriptErrors();
});
