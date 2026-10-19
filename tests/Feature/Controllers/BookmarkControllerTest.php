<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('saves a visible commit idempotently for the authenticated user', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();

    $this->actingAs($user)->putJson('/commits/'.$commit->id.'/bookmark')
        ->assertOk()->assertExactJson(['is_saved' => true]);
    $this->putJson('/commits/'.$commit->id.'/bookmark')
        ->assertOk()->assertExactJson(['is_saved' => true]);

    $this->assertDatabaseCount('bookmarks', 1);
    $this->assertDatabaseHas('bookmarks', ['user_id' => $user->id, 'commit_id' => $commit->id]);
    $this->assertDatabaseCount('notifications', 0);
});

it('renders a private empty saved page', function (): void {
    $this->actingAs(User::factory()->create())->get('/saved')
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('bookmark/index')
        ->has('commits.data', 0)
        ->where('commits.next_cursor', null));
});

it('includes link previews on saved commits and replies', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for(LinkPreview::factory()->fetched()->state(['title' => 'Saved commit link']))->create();
    $reply = Commit::factory()->for($commit, 'parent')
        ->for(LinkPreview::factory()->fetched()->state(['title' => 'Saved reply link']))->create();

    $this->actingAs($user)->putJson(route('bookmark.store', $commit))->assertOk();
    $this->putJson(route('bookmark.store', $reply))->assertOk();

    $this->get(route('bookmark.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data', 2)
        ->where('commits.data.0.id', $reply->id)
        ->where('commits.data.0.link_preview.title', 'Saved reply link')
        ->where('commits.data.1.id', $commit->id)
        ->where('commits.data.1.link_preview.title', 'Saved commit link'));
});

it('includes the quoted commits of saved commits', function (): void {
    $user = User::factory()->create();
    $quoted = Commit::factory()->for(User::factory()->state(['username' => 'taylorotwell']))->create(['body' => 'The quoted commit']);
    $quote = Commit::factory()->for($quoted, 'quotedCommit')->create();
    $hiddenQuote = Commit::factory()->for(Commit::factory()->pending(), 'quotedCommit')->create(['created_at' => now()->subMinute()]);

    $this->actingAs($user)->putJson(route('bookmark.store', $hiddenQuote))->assertOk();
    $this->putJson(route('bookmark.store', $quote))->assertOk();

    $this->get(route('bookmark.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data', 2)
        ->where('commits.data.0.quoted_commit.body', 'The quoted commit')
        ->where('commits.data.0.quoted_commit.user.username', 'taylorotwell')
        ->where('commits.data.1.quoted_commit_id', $hiddenQuote->quoted_commit_id)
        ->where('commits.data.1.quoted_commit', null));
});

it('includes images on saved commits', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $image = CommitImage::factory()->for($commit)->create();

    $this->actingAs($user)->putJson(route('bookmark.store', $commit))->assertOk();

    $this->get(route('bookmark.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data.0.images', 1)
        ->where('commits.data.0.images.0.id', $image->id));
});

it('requires authentication for the saved page and mutations', function (): void {
    $commit = Commit::factory()->create();

    $this->get('/saved')->assertRedirectToRoute('home');
    $this->putJson(route('bookmark.store', $commit))->assertUnauthorized();
    $this->deleteJson(route('bookmark.destroy', $commit))->assertUnauthorized();
    $this->assertDatabaseCount('bookmarks', 0);
});

it('ignores supplied ownership and removes only the viewers save idempotently', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $commit = Commit::factory()->create();
    $bookmark = Bookmark::factory()->create(['user_id' => $other->id, 'commit_id' => $commit->id]);

    $this->actingAs($user)->putJson(route('bookmark.store', $commit), ['user_id' => $other->id])
        ->assertOk();
    $this->assertDatabaseCount('bookmarks', 2);

    foreach (range(1, 2) as $attempt) {
        $this->deleteJson(route('bookmark.destroy', $commit), ['user_id' => $other->id, 'id' => $bookmark->id])
            ->assertOk()->assertExactJson(['is_saved' => false]);
    }

    $this->assertDatabaseCount('bookmarks', 1);
    $this->assertModelExists($bookmark);
});

it('saves and lists own commits in every moderation state', function (CommitStatus $status): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create(['status' => $status]);
    $commit->stars()->attach($user);

    $this->actingAs($user)->putJson(route('bookmark.store', $commit))->assertOk();
    $this->get(route('bookmark.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data', 1)
        ->where('commits.data.0.id', $commit->id)
        ->where('commits.data.0.status', $status->value)
        ->where('commits.data.0.stars_count', 1)
        ->where('commits.data.0.starred', true)
        ->where('commits.data.0.is_saved', true));
    $this->deleteJson(route('bookmark.destroy', $commit))->assertOk();
})->with(CommitStatus::cases());

it('never exposes or mutates another authors unavailable commit even to admins', function (CommitStatus $status): void {
    $user = User::factory()->create();
    config()->set('app.admins', [$user->username]);
    $commit = Commit::factory()->create();
    $bookmark = Bookmark::factory()->create(['user_id' => $user->id, 'commit_id' => $commit->id]);
    $commit->update(['status' => $status]);

    $this->actingAs($user)->putJson(route('bookmark.store', $commit))->assertNotFound();
    $this->deleteJson(route('bookmark.destroy', $commit))->assertNotFound();
    $this->get(route('bookmark.index'))->assertOk()->assertInertia(fn ($page) => $page->has('commits.data', 0));
    $this->assertModelExists($bookmark);
})->with([CommitStatus::Pending, CommitStatus::Rejected]);

it('excludes deleted commits even when they have visible replies and belong to the viewer', function (bool $own): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($own ? $user : User::factory())->create();
    Commit::factory()->for($commit, 'parent')->create();
    Bookmark::factory()->create(['user_id' => $user->id, 'commit_id' => $commit->id]);
    $commit->delete();

    $this->actingAs($user)->get(route('bookmark.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('commits.data', 0));
    $this->putJson(route('bookmark.store', $commit))->assertNotFound();
    $this->deleteJson(route('bookmark.destroy', $commit))->assertNotFound();
})->with([true, false]);

it('returns not found for a missing commit', function (): void {
    $this->actingAs(User::factory()->create())
        ->putJson('/commits/00000000-0000-0000-0000-000000000000/bookmark')->assertNotFound();
    $this->deleteJson('/commits/00000000-0000-0000-0000-000000000000/bookmark')->assertNotFound();
});

it('keeps saved lists private and newest saved first without bumping duplicate saves', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $newerCommit = Commit::factory()->create();
    $olderCommit = Commit::factory()->create(['created_at' => now()->subYear()]);
    Bookmark::factory()->create(['user_id' => $other->id]);

    $this->actingAs($user)->putJson(route('bookmark.store', $newerCommit))->assertOk();
    $this->putJson(route('bookmark.store', $olderCommit))->assertOk();
    $this->putJson(route('bookmark.store', $newerCommit))->assertOk();

    $this->get(route('bookmark.index', ['user_id' => $other->id]))->assertInertia(fn ($page) => $page
        ->has('commits.data', 2)
        ->where('commits.data.0.id', $olderCommit->id)
        ->where('commits.data.1.id', $newerCommit->id)
        ->missing('commits.data.0.bookmarks')
        ->missing('commits.data.0.bookmarks_count')
        ->missing('commits.data.0.user.email'));

    $this->deleteJson(route('bookmark.destroy', $newerCommit))->assertOk();
    $this->putJson(route('bookmark.store', $newerCommit))->assertOk();
    $this->get(route('bookmark.index'))->assertInertia(fn ($page) => $page
        ->where('commits.data.0.id', $newerCommit->id));
});

it('paginates saves with identical timestamps without duplicates and excludes newly unavailable content', function (): void {
    $user = User::factory()->create();
    $commits = Commit::factory()->count(25)->create();

    foreach ($commits as $commit) {
        Bookmark::factory()->create(['user_id' => $user->id, 'commit_id' => $commit->id]);
    }

    $response = $this->actingAs($user)->get(route('bookmark.index'))->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('commits.data', 20)
        ->where('commits.data.0.id', $commits->last()->id));
    $cursor = $response->inertiaProps('commits.next_cursor');
    $commits[0]->delete();
    $commits[1]->update(['status' => CommitStatus::Rejected]);

    $this->deleteJson(route('bookmark.destroy', $commits->last()))->assertOk();
    $this->get(route('bookmark.index', ['cursor' => $cursor]))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 3)
            ->where('commits.data.0.id', $commits[4]->id)
            ->where('commits.data.2.id', $commits[2]->id)
            ->where('commits.next_cursor', null));
});

it('includes saved replies without leaking invisible parents or reply counts', function (string $parentState): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    Commit::factory()->for($reply, 'parent')->create();
    Commit::factory()->for($reply, 'parent')->pending()->create();
    Commit::factory()->for($reply, 'parent')->for($user)->rejected()->create();

    if ($parentState === 'deleted') {
        $parent->delete();
    } else {
        $parent->update(['status' => CommitStatus::Pending]);
    }

    $this->actingAs($user)->putJson(route('bookmark.store', $reply))->assertOk();
    $this->get(route('bookmark.index'))->assertOk()->assertInertia(function ($page) use ($parentState, $reply): void {
        $page->has('commits.data', 1)
            ->where('commits.data.0.id', $reply->id)
            ->where('commits.data.0.replies_count', 2);

        if ($parentState === 'deleted') {
            $page->has('commits.data.0.parent.deleted_at')
                ->missing('commits.data.0.parent.user')
                ->missing('commits.data.0.parent.body');
        } else {
            $page->where('commits.data.0.parent', null);
        }
    });
})->with(['pending', 'deleted']);

it('returns only the viewers saved state across logs profiles commit pages and replies', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $commit = Commit::factory()->for($other)->create();
    $reply = Commit::factory()->for($other)->for($commit, 'parent')->create();
    Bookmark::factory()->create(['user_id' => $user->id, 'commit_id' => $commit->id]);
    Bookmark::factory()->create(['user_id' => $user->id, 'commit_id' => $reply->id]);
    $user->following()->attach($other);

    foreach ([route('log.show', 'trending'), route('log.show', 'following'), route('user.show', $other), route('user.show', [$other, 'tab' => 'replies'])] as $url) {
        $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->where('commits.data.0.is_saved', true));
        $this->actingAs($other)->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->where('commits.data.0.is_saved', false));
    }

    $this->actingAs($user)->get(route('commit.show', [$other, $commit]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.is_saved', true)
        ->where('continuation.data.0.is_saved', true));
    $this->getJson(route('commit-reply.index', $commit))->assertOk()->assertJsonPath('data.0.is_saved', true);
    $this->get(route('commit.show', [$reply->user, $reply]))->assertOk()->assertInertia(fn ($page) => $page
        ->where('ancestors.0.is_saved', true));
});

it('enforces uniqueness at the database boundary', function (): void {
    $bookmark = Bookmark::factory()->create();

    expect(fn () => Bookmark::factory()->create(['user_id' => $bookmark->user_id, 'commit_id' => $bookmark->commit_id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('removes bookmarks when the saver or commit is permanently deleted', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    Bookmark::factory()->create(['user_id' => $user->id, 'commit_id' => $commit->id]);
    $commit->forceDelete();
    $this->assertDatabaseCount('bookmarks', 0);

    Bookmark::factory()->create(['user_id' => $user->id]);
    $user->delete();
    $this->assertDatabaseCount('bookmarks', 0);
});

it('loads saved commit authors and state with a bounded number of queries', function (): void {
    $user = User::factory()->create(['last_seen_at' => today()]);
    Bookmark::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);
    DB::enableQueryLog();

    $this->get(route('bookmark.index'))->assertOk();
    $singleCount = count(DB::getQueryLog());
    Bookmark::factory()->count(19)->create(['user_id' => $user->id]);
    DB::flushQueryLog();

    $this->get(route('bookmark.index'))->assertOk()->assertInertia(fn ($page) => $page->has('commits.data', 20));
    expect(count(DB::getQueryLog()))->toBe($singleCount);
    DB::disableQueryLog();
});
