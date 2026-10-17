<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Bookmark;
use App\Models\BookmarkCollection;
use App\Models\Commit;
use App\Models\User;

it('adds a saved commit to a collection idempotently', function (): void {
    $user = User::factory()->create();
    $bookmark = Bookmark::factory()->for($user)->create();
    $collection = BookmarkCollection::factory()->for($user)->create();

    foreach (range(1, 2) as $attempt) {
        $this->actingAs($user)->putJson(route('bookmark-collection-commit.store', [$collection, $bookmark->commit]))
            ->assertOk()->assertExactJson(['in_collection' => true]);
    }

    $this->assertDatabaseCount('bookmark_collection_items', 1);
    $this->assertDatabaseHas('bookmark_collection_items', ['bookmark_collection_id' => $collection->id, 'bookmark_id' => $bookmark->id]);
});

it('adds a commit to several collections and removes it from one while keeping the rest and the save', function (): void {
    $user = User::factory()->create();
    $bookmark = Bookmark::factory()->for($user)->create();
    $tips = BookmarkCollection::factory()->for($user)->create(['name' => 'Laravel Tips']);
    $packages = BookmarkCollection::factory()->for($user)->create(['name' => 'Useful Packages']);

    $this->actingAs($user)->putJson(route('bookmark-collection-commit.store', [$tips, $bookmark->commit]))->assertOk();
    $this->putJson(route('bookmark-collection-commit.store', [$packages, $bookmark->commit]))->assertOk();
    $this->assertDatabaseCount('bookmark_collection_items', 2);

    foreach (range(1, 2) as $attempt) {
        $this->deleteJson(route('bookmark-collection-commit.destroy', [$tips, $bookmark->commit]))
            ->assertOk()->assertExactJson(['in_collection' => false]);
    }

    $this->assertModelExists($bookmark);
    $this->assertDatabaseMissing('bookmark_collection_items', ['bookmark_collection_id' => $tips->id]);
    $this->assertDatabaseHas('bookmark_collection_items', ['bookmark_collection_id' => $packages->id, 'bookmark_id' => $bookmark->id]);

    $this->get(route('bookmark.index'))->assertInertia(fn ($page) => $page
        ->has('commits.data', 1)
        ->where('commits.data.0.collection_ids', [$packages->id])
        ->where('collections.0.id', $tips->id)
        ->where('collections.0.commits_count', 0)
        ->where('collections.1.id', $packages->id)
        ->where('collections.1.commits_count', 1));
    $this->get(route('bookmark-collection.show', $tips))->assertInertia(fn ($page) => $page->has('commits.data', 0));
});

it('refuses to add a commit the viewer has not saved', function (): void {
    $user = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create();
    $commit = Commit::factory()->create();
    Bookmark::factory()->create(['commit_id' => $commit->id]);

    $this->actingAs($user)->putJson(route('bookmark-collection-commit.store', [$collection, $commit]))
        ->assertForbidden()
        ->assertJsonPath('message', 'Save this commit before adding it to a collection.');

    $this->assertDatabaseCount('bookmark_collection_items', 0);
});

it('removes a commit from every collection when it is unsaved, keeping the collections', function (): void {
    $user = User::factory()->create();
    $bookmark = Bookmark::factory()->for($user)->create();
    $kept = Bookmark::factory()->for($user)->create();
    $collections = BookmarkCollection::factory()->for($user)->count(2)->create();

    foreach ($collections as $collection) {
        $collection->bookmarks()->attach([$bookmark->id, $kept->id]);
    }

    $this->actingAs($user)->deleteJson(route('bookmark.destroy', $bookmark->commit))->assertOk();

    $this->assertModelMissing($bookmark);
    $this->assertDatabaseCount('bookmark_collections', 2);
    $this->assertDatabaseMissing('bookmark_collection_items', ['bookmark_id' => $bookmark->id]);
    $this->assertDatabaseCount('bookmark_collection_items', 2);

    $this->putJson(route('bookmark.store', $bookmark->commit))->assertOk();
    $this->get(route('bookmark.index'))->assertInertia(fn ($page) => $page
        ->where('commits.data.0.id', $bookmark->commit_id)
        ->where('commits.data.0.collection_ids', [])
        ->where('collections.0.commits_count', 1)
        ->where('collections.1.commits_count', 1));
});

it('removes assignments when the saved commit is permanently deleted', function (): void {
    $user = User::factory()->create();
    $bookmark = Bookmark::factory()->for($user)->create();
    $collection = BookmarkCollection::factory()->for($user)->create();
    $collection->bookmarks()->attach($bookmark);

    $bookmark->commit->forceDelete();

    $this->assertModelExists($collection);
    $this->assertDatabaseCount('bookmark_collection_items', 0);
});

it('never assigns another users save to the viewers collection', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create();
    $othersBookmark = Bookmark::factory()->for($other)->create();
    $ownBookmark = Bookmark::factory()->for($user)->create(['commit_id' => Commit::factory()->create()->id]);

    $this->actingAs($user)->putJson(route('bookmark-collection-commit.store', [$collection, $othersBookmark->commit]), ['bookmark_id' => $othersBookmark->id, 'user_id' => $other->id])
        ->assertForbidden();
    $this->putJson(route('bookmark-collection-commit.store', [$collection, $ownBookmark->commit]), ['bookmark_id' => $othersBookmark->id])
        ->assertOk();

    $this->assertDatabaseCount('bookmark_collection_items', 1);
    $this->assertDatabaseHas('bookmark_collection_items', ['bookmark_collection_id' => $collection->id, 'bookmark_id' => $ownBookmark->id]);
});

it('never touches another users assignments when removing', function (): void {
    $other = User::factory()->create();
    $othersBookmark = Bookmark::factory()->for($other)->create();
    $othersCollection = BookmarkCollection::factory()->for($other)->create();
    $othersCollection->bookmarks()->attach($othersBookmark);
    $user = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create();

    $this->actingAs($user)->deleteJson(route('bookmark-collection-commit.destroy', [$collection, $othersBookmark->commit]))->assertOk();
    $this->deleteJson(route('bookmark-collection-commit.destroy', [$othersCollection, $othersBookmark->commit]))->assertNotFound();

    $this->assertDatabaseHas('bookmark_collection_items', ['bookmark_collection_id' => $othersCollection->id, 'bookmark_id' => $othersBookmark->id]);
});

it('hides commits the viewer can no longer see', function (CommitStatus $status): void {
    $user = User::factory()->create();
    $bookmark = Bookmark::factory()->for($user)->create();
    $collection = BookmarkCollection::factory()->for($user)->create();
    $bookmark->commit->update(['status' => $status]);

    $this->actingAs($user)->putJson(route('bookmark-collection-commit.store', [$collection, $bookmark->commit]))->assertNotFound();
    $this->deleteJson(route('bookmark-collection-commit.destroy', [$collection, $bookmark->commit]))->assertNotFound();

    $this->assertDatabaseCount('bookmark_collection_items', 0);
})->with([CommitStatus::Pending, CommitStatus::Rejected]);

it('returns not found for a deleted commit', function (): void {
    $user = User::factory()->create();
    $bookmark = Bookmark::factory()->for($user)->create();
    $collection = BookmarkCollection::factory()->for($user)->create();
    $bookmark->commit->delete();

    $this->actingAs($user)->putJson(route('bookmark-collection-commit.store', [$collection, $bookmark->commit_id]))->assertNotFound();
});

it('keeps collections out of every public and shared response', function (): void {
    $author = User::factory()->create(['username' => 'author']);
    $commit = Commit::factory()->for($author)->create();
    $bookmark = Bookmark::factory()->for($author)->create(['commit_id' => $commit->id]);
    $collection = BookmarkCollection::factory()->for($author)->create(['name' => 'Top secret shelf']);
    $collection->bookmarks()->attach($bookmark);
    $viewer = User::factory()->create();
    $viewer->following()->attach($author);

    $urls = [
        route('user.show', $author),
        route('commit.show', [$author, $commit]),
        route('user-feed.show', $author),
        route('user-preview.show', $author),
        route('sitemap.index'),
        route('commit-sitemap.show', 1),
        '/api/v1/users/author',
        '/api/v1/users/author/commits',
        '/api/v1/commits/'.$commit->id,
    ];

    foreach ([null, $viewer, $author] as $user) {
        if ($user instanceof User) {
            $this->actingAs($user);
        }

        foreach ($urls as $url) {
            $content = (string) $this->get($url)->assertOk()->getContent();

            expect($content)
                ->not->toContain('Top secret shelf')
                ->not->toContain($collection->id)
                ->not->toContain('collection_ids');
        }
    }

    foreach ([route('log.show', 'following'), route('log.show', 'trending'), route('commit.show', [$author, $commit])] as $url) {
        $this->actingAs($author)->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->missing('collections')
            ->missing('commits.data.0.collection_ids')
            ->missing('commit.collection_ids'));
    }
});
