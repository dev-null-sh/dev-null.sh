<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('includes known mentions on logs profiles and commit pages without changing body or previews', function (): void {
    $viewer = User::factory()->create();
    $mentioned = User::factory()->create(['username' => 'dev-one']);
    $preview = LinkPreview::factory()->create();
    $commit = Commit::factory()->for($viewer)->for($preview)->create(['body' => 'Thanks @dev-one, @unknown https://example.com/@dev-one']);

    $this->actingAs($viewer);
    foreach ([route('log.show', Log::Everyone), route('log.show', Log::Following), route('user.show', $viewer)] as $url) {
        $this->get($url)->assertInertia(fn (Assert $page): Assert => $page
            ->where('commits.data.0.body', $commit->body)
            ->where('commits.data.0.mentions', [$mentioned->username])
            ->where('commits.data.0.link_preview.url', $preview->url));
    }

    $this->get(route('commit.show', [$viewer, $commit]))->assertInertia(fn (Assert $page): Assert => $page
        ->where('commit.mentions', [$mentioned->username]));
});

it('resolves visible reply mentions while preserving moderation and tombstones', function (): void {
    $viewer = User::factory()->create();
    User::factory()->create(['username' => 'dev-one']);
    $parent = Commit::factory()->create();
    Commit::factory()->for($parent, 'parent')->create(['body' => 'Approved @dev-one', 'created_at' => now()->subMinutes(5)]);
    Commit::factory()->for($parent, 'parent')->for($viewer)->pending()->create(['body' => 'Own pending @dev-one', 'created_at' => now()->subMinutes(4)]);
    Commit::factory()->for($parent, 'parent')->for($viewer)->rejected()->create(['body' => 'Own rejected @dev-one', 'created_at' => now()->subMinutes(3)]);
    Commit::factory()->for($parent, 'parent')->pending()->create(['body' => 'Hidden pending @dev-one']);
    Commit::factory()->for($parent, 'parent')->rejected()->create(['body' => 'Hidden rejected @dev-one']);
    $deleted = Commit::factory()->for($parent, 'parent')->create(['body' => 'Secret @dev-one', 'created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($deleted, 'parent')->create();
    $deleted->delete();

    $this->actingAs($viewer)->getJson(route('commit-reply.index', $parent))
        ->assertOk()->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.mentions', ['dev-one'])
        ->assertJsonPath('data.1.mentions', ['dev-one'])
        ->assertJsonPath('data.2.mentions', ['dev-one'])
        ->assertJsonMissingPath('data.3.body')->assertJsonMissingPath('data.3.mentions')
        ->assertJsonMissing(['body' => 'Hidden pending @dev-one'])
        ->assertJsonMissing(['body' => 'Hidden rejected @dev-one']);
});

it('does not expose mentions in inaccessible commits or to guests', function (): void {
    $viewer = User::factory()->create();
    User::factory()->create(['username' => 'dev-one']);
    $hidden = Commit::factory()->pending()->create(['body' => 'Private @dev-one']);
    $this->actingAs($viewer)->get(route('commit.show', [$hidden->user, $hidden]))->assertNotFound();
    $this->getJson(route('commit-reply.index', $hidden))->assertNotFound();
    auth()->logout();
    $this->getJson(route('user-search.index', ['q' => 'dev']))->assertUnauthorized();
});

it('resolves ancestor mentions on a reply page', function (): void {
    $viewer = User::factory()->create();
    User::factory()->create(['username' => 'dev-one']);
    $parent = Commit::factory()->create(['body' => 'Parent @dev-one']);
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $this->actingAs($viewer)->get(route('commit.show', [$reply->user, $reply]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('ancestors.0.mentions', ['dev-one']));
});

it('preserves mentions previews and engagement state on saved commits and replies', function (): void {
    $viewer = User::factory()->create();
    User::factory()->create(['username' => 'dev-one']);
    $preview = LinkPreview::factory()->fetched()->create();
    $commit = Commit::factory()->for($preview)->create(['body' => 'Read @dev-one https://example.com']);
    $reply = Commit::factory()->for($commit, 'parent')->for($preview)->create(['body' => 'Thanks @dev-one https://example.com']);
    $reply->stars()->attach($viewer);

    $this->actingAs($viewer)->putJson(route('bookmark.store', $commit))->assertOk();
    $this->putJson(route('bookmark.store', $reply))->assertOk();

    $this->get(route('bookmark.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->has('commits.data', 2)
        ->where('commits.data.0.id', $reply->id)
        ->where('commits.data.0.body', $reply->body)
        ->where('commits.data.0.mentions', ['dev-one'])
        ->where('commits.data.0.link_preview.url', $preview->url)
        ->where('commits.data.0.parent.id', $commit->id)
        ->where('commits.data.0.starred', true)
        ->where('commits.data.0.stars_count', 1)
        ->where('commits.data.0.is_saved', true)
        ->where('commits.data.1.id', $commit->id)
        ->where('commits.data.1.body', $commit->body)
        ->where('commits.data.1.mentions', ['dev-one'])
        ->where('commits.data.1.link_preview.url', $preview->url)
        ->where('commits.data.1.is_saved', true));
});

it('includes mentions and previews in the profile replies tab', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    User::factory()->create(['username' => 'dev-one']);
    $preview = LinkPreview::factory()->fetched()->create();
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($author)->for($parent, 'parent')->for($preview)
        ->create(['body' => 'Reply to @dev-one https://example.com']);
    Commit::factory()->for($author)->create(['body' => 'Top level @dev-one']);
    Commit::factory()->for($author)->for($parent, 'parent')->pending()->create(['body' => 'Hidden @dev-one']);

    $this->actingAs($viewer)->get(route('user.show', ['user' => $author, 'tab' => 'replies']))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('tab', 'replies')
        ->has('commits.data', 1)
        ->where('commits.data.0.id', $reply->id)
        ->where('commits.data.0.body', $reply->body)
        ->where('commits.data.0.mentions', ['dev-one'])
        ->where('commits.data.0.link_preview.url', $preview->url)
        ->where('commits.data.0.parent.id', $parent->id));
});

it('includes mentions on a pinned profile commit', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    User::factory()->create(['username' => 'dev-one']);
    $pinned = Commit::factory()->for($author)->create(['body' => 'Pinned thanks to @dev-one']);
    $author->update(['pinned_commit_id' => $pinned->id]);

    $this->actingAs($viewer)->get(route('user.show', $author))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('pinnedCommit.id', $pinned->id)
        ->where('pinnedCommit.body', $pinned->body)
        ->where('pinnedCommit.mentions', ['dev-one']));
});
