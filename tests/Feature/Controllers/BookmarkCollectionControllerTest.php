<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Bookmark;
use App\Models\BookmarkCollection;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('creates a collection with a trimmed name and opens it', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('bookmark-collection.store'), ['name' => '  Laravel Tips  ']);

    $collection = BookmarkCollection::query()->sole();
    $response->assertRedirectToRoute('bookmark-collection.show', $collection)
        ->assertInertiaFlash('toast.message', 'Collection created.');
    expect($collection->name)->toBe('Laravel Tips')
        ->and($collection->user_id)->toBe($user->id);
});

it('ignores a supplied owner when creating a collection', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->post(route('bookmark-collection.store'), ['name' => 'Read Later', 'user_id' => $other->id])
        ->assertRedirect();

    expect(BookmarkCollection::query()->sole()->user_id)->toBe($user->id);
});

it('rejects invalid collection names', function (mixed $name): void {
    $this->actingAs(User::factory()->create())
        ->from(route('bookmark.index'))
        ->post(route('bookmark-collection.store'), ['name' => $name])
        ->assertRedirectToRoute('bookmark.index')
        ->assertSessionHasErrors('name');

    $this->assertDatabaseCount('bookmark_collections', 0);
})->with([
    'missing' => null,
    'empty' => '',
    'whitespace only' => '    ',
    'too long' => str_repeat('a', 51),
    'not a string' => [['Laravel']],
]);

it('accepts names up to fifty characters', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('bookmark-collection.store'), ['name' => str_repeat('a', 50)])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('bookmark_collections', 1);
});

it('rejects a duplicate name for the same user ignoring case', function (string $name): void {
    $user = User::factory()->create();
    BookmarkCollection::factory()->for($user)->create(['name' => 'Laravel Tips']);

    $this->actingAs($user)->post(route('bookmark-collection.store'), ['name' => $name])
        ->assertSessionHasErrors(['name' => 'You already have a collection with this name.']);

    $this->assertDatabaseCount('bookmark_collections', 1);
})->with(['Laravel Tips', 'laravel tips', ' LARAVEL TIPS ']);

it('allows different users to use the same name', function (): void {
    BookmarkCollection::factory()->create(['name' => 'Laravel Tips']);

    $this->actingAs(User::factory()->create())
        ->post(route('bookmark-collection.store'), ['name' => 'Laravel Tips'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('bookmark_collections', 2);
});

it('limits how many collections a user can have', function (): void {
    $user = User::factory()->create();
    BookmarkCollection::factory()->for($user)->count(BookmarkCollection::MAX_PER_USER)->create();

    $this->actingAs($user)->post(route('bookmark-collection.store'), ['name' => 'One too many'])
        ->assertSessionHasErrors(['name' => 'You can have up to 50 collections. Delete one before creating another.']);

    $this->assertDatabaseCount('bookmark_collections', BookmarkCollection::MAX_PER_USER);
});

it('renames a collection', function (): void {
    $user = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create(['name' => 'Laravel']);

    $this->actingAs($user)->from(route('bookmark-collection.show', $collection))
        ->put(route('bookmark-collection.update', $collection), ['name' => ' Laravel Tips '])
        ->assertRedirectToRoute('bookmark-collection.show', $collection)
        ->assertInertiaFlash('toast.message', 'Collection renamed.');

    expect($collection->refresh()->name)->toBe('Laravel Tips');
});

it('renames a collection to a different case of its own name', function (): void {
    $user = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create(['name' => 'laravel tips']);

    $this->actingAs($user)->put(route('bookmark-collection.update', $collection), ['name' => 'Laravel Tips'])
        ->assertSessionHasNoErrors();

    expect($collection->refresh()->name)->toBe('Laravel Tips');
});

it('rejects renaming to another collections name or an invalid name', function (string $name): void {
    $user = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create(['name' => 'Packages']);
    BookmarkCollection::factory()->for($user)->create(['name' => 'Laravel Tips']);

    $this->actingAs($user)->put(route('bookmark-collection.update', $collection), ['name' => $name])
        ->assertSessionHasErrors('name');

    expect($collection->refresh()->name)->toBe('Packages');
})->with(['LARAVEL TIPS', '   ', 'x'.str_repeat('y', 50)]);

it('deletes a collection without unsaving its commits or touching other collections', function (): void {
    $user = User::factory()->create();
    $bookmark = Bookmark::factory()->for($user)->create();
    $collection = BookmarkCollection::factory()->for($user)->create();
    $other = BookmarkCollection::factory()->for($user)->create();
    $collection->bookmarks()->attach($bookmark);
    $other->bookmarks()->attach($bookmark);

    $this->actingAs($user)->delete(route('bookmark-collection.destroy', $collection))
        ->assertRedirectToRoute('bookmark.index')
        ->assertInertiaFlash('toast.message', 'Collection deleted. Its commits are still saved.');

    $this->assertModelMissing($collection);
    $this->assertModelExists($bookmark);
    $this->assertModelExists($other);
    $this->assertDatabaseHas('bookmark_collection_items', ['bookmark_collection_id' => $other->id, 'bookmark_id' => $bookmark->id]);
    $this->assertDatabaseMissing('bookmark_collection_items', ['bookmark_collection_id' => $collection->id]);
});

it('hides another users collection as if it did not exist', function (): void {
    $owner = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($owner)->create(['name' => 'Secret']);
    $intruder = User::factory()->create();
    $commit = Bookmark::factory()->for($intruder)->create()->commit;

    $this->actingAs($intruder);
    $this->get(route('bookmark-collection.show', $collection))->assertNotFound();
    $this->put(route('bookmark-collection.update', $collection), ['name' => 'Mine now'])->assertNotFound();
    $this->delete(route('bookmark-collection.destroy', $collection))->assertNotFound();
    $this->putJson(route('bookmark-collection-commit.store', [$collection, $commit]))->assertNotFound();
    $this->deleteJson(route('bookmark-collection-commit.destroy', [$collection, $commit]))->assertNotFound();

    $this->assertModelExists($collection);
    expect($collection->refresh()->name)->toBe('Secret');
    $this->assertDatabaseCount('bookmark_collection_items', 0);
});

it('returns not found for a missing or malformed collection', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get('/saved/collections/00000000-0000-0000-0000-000000000000')->assertNotFound();
    $this->get('/saved/collections/not-a-uuid')->assertNotFound();
});

it('requires authentication for every collection endpoint', function (): void {
    $collection = BookmarkCollection::factory()->create();
    $commit = Commit::factory()->create();

    $this->get(route('bookmark-collection.show', $collection))->assertRedirectToRoute('home');
    $this->postJson(route('bookmark-collection.store'), ['name' => 'Tips'])->assertUnauthorized();
    $this->putJson(route('bookmark-collection.update', $collection), ['name' => 'Tips'])->assertUnauthorized();
    $this->deleteJson(route('bookmark-collection.destroy', $collection))->assertUnauthorized();
    $this->putJson(route('bookmark-collection-commit.store', [$collection, $commit]))->assertUnauthorized();
    $this->deleteJson(route('bookmark-collection-commit.destroy', [$collection, $commit]))->assertUnauthorized();

    $this->assertModelExists($collection);
    $this->assertDatabaseCount('bookmark_collections', 1);
});

it('lists the viewers collections by name with counts of visible saved commits', function (): void {
    $user = User::factory()->create();
    $tips = BookmarkCollection::factory()->for($user)->create(['name' => 'tips']);
    $packages = BookmarkCollection::factory()->for($user)->create(['name' => 'Packages']);
    BookmarkCollection::factory()->create(['name' => 'Someone elses']);
    $visible = Bookmark::factory()->for($user)->create();
    $deleted = Bookmark::factory()->for($user)->create();
    $hidden = Bookmark::factory()->for($user)->create();
    $tips->bookmarks()->attach([$visible->id, $deleted->id, $hidden->id]);
    $deleted->commit->delete();
    $hidden->commit->update(['status' => CommitStatus::Rejected]);

    $this->actingAs($user)->get(route('bookmark.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('bookmark/index')
        ->where('collection', null)
        ->has('collections', 2)
        ->where('collections.0', ['id' => $packages->id, 'name' => 'Packages', 'commits_count' => 0])
        ->where('collections.1', ['id' => $tips->id, 'name' => 'tips', 'commits_count' => 1])
        ->has('commits.data', 1)
        ->where('commits.data.0.id', $visible->commit_id)
        ->where('commits.data.0.collection_ids', [$tips->id]));
});

it('shows a collection with only its saved commits newest saved first', function (): void {
    $user = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create(['name' => 'Debugging']);
    $other = BookmarkCollection::factory()->for($user)->create();
    $older = Bookmark::factory()->for($user)->create();
    $outside = Bookmark::factory()->for($user)->create();
    $newer = Bookmark::factory()->for($user)->create();
    $collection->bookmarks()->attach([$older->id, $newer->id]);
    $other->bookmarks()->attach([$newer->id, $outside->id]);
    Bookmark::factory()->create(['commit_id' => $older->commit_id]);

    $this->actingAs($user)->get(route('bookmark-collection.show', $collection))->assertOk()->assertInertia(fn ($page) => $page
        ->component('bookmark/index')
        ->where('collection', ['id' => $collection->id, 'name' => 'Debugging'])
        ->has('collections', 2)
        ->has('commits.data', 2)
        ->where('commits.data.0.id', $newer->commit_id)
        ->where('commits.data.0.collection_ids', collect([$collection->id, $other->id])->sort()->values()->all())
        ->where('commits.data.0.is_saved', true)
        ->where('commits.data.1.id', $older->commit_id)
        ->where('commits.data.1.collection_ids', [$collection->id]));
});

it('renders an empty new collection', function (): void {
    $user = User::factory()->create();
    Bookmark::factory()->for($user)->create();
    $collection = BookmarkCollection::factory()->for($user)->create();

    $this->actingAs($user)->get(route('bookmark-collection.show', $collection))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data', 0)
        ->where('commits.next_cursor', null)
        ->where('collections.0.commits_count', 0));
});

it('paginates a collection with a cursor', function (): void {
    $user = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create();
    $bookmarks = Bookmark::factory()->for($user)->count(23)->create();
    $collection->bookmarks()->attach($bookmarks->modelKeys());
    Bookmark::factory()->for($user)->count(3)->create();

    $response = $this->actingAs($user)->get(route('bookmark-collection.show', $collection))->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('commits.data', 20)
        ->where('commits.data.0.id', $bookmarks->last()->commit_id));

    $this->get(route('bookmark-collection.show', [$collection, 'cursor' => $response->inertiaProps('commits.next_cursor')]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data', 3)
        ->where('commits.data.2.id', $bookmarks->first()->commit_id)
        ->where('commits.next_cursor', null));
});

it('enforces unique names per user at the database boundary', function (): void {
    $collection = BookmarkCollection::factory()->create();

    expect(fn () => BookmarkCollection::factory()->create(['user_id' => $collection->user_id, 'name' => $collection->name]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('removes collections and their assignments when the owner is deleted', function (): void {
    $user = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create();
    $collection->bookmarks()->attach(Bookmark::factory()->for($user)->create());

    $user->delete();

    $this->assertDatabaseCount('bookmark_collections', 0);
    $this->assertDatabaseCount('bookmark_collection_items', 0);
});

it('loads collection memberships with a bounded number of queries', function (): void {
    $user = User::factory()->create();
    $collection = BookmarkCollection::factory()->for($user)->create();
    $collection->bookmarks()->attach(Bookmark::factory()->for($user)->create());
    $this->actingAs($user);
    DB::enableQueryLog();

    $this->get(route('bookmark.index'))->assertOk();
    $singleCount = count(DB::getQueryLog());
    $collection->bookmarks()->attach(Bookmark::factory()->for($user)->count(19)->create()->modelKeys());
    DB::flushQueryLog();

    $this->get(route('bookmark.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data', 20)
        ->where('commits.data.19.collection_ids', [$collection->id]));
    expect(count(DB::getQueryLog()))->toBe($singleCount);
    DB::disableQueryLog();
});
