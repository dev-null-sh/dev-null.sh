<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;

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
