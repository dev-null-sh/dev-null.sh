<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('saves a commit and answers with it saved', function (): void {
    $commit = Commit::factory()->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $commit));

    $response->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.is_saved', true);

    $this->assertDatabaseHas('bookmarks', ['user_id' => $this->user->id, 'commit_id' => $commit->id]);
});

it('answers with the commit as the commit endpoint shows it', function (): void {
    $commit = Commit::factory()->create();
    $commit->stars()->attach(User::factory()->create());

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $commit));

    $response->assertOk()
        ->assertExactJson(['data' => signedApiRequest($this->keyPair, 'GET', route('api-commit.show', $commit))->json('data')]);
});

it('saves a commit only once', function (): void {
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $commit))->assertOk();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $commit));

    $response->assertOk()
        ->assertJsonPath('data.is_saved', true);

    $this->assertDatabaseCount('bookmarks', 1);
});

it('saves for the user who signed the request, whatever the body says', function (): void {
    $other = User::factory()->create();
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $commit), ['user_id' => $other->id])->assertOk();

    $this->assertDatabaseCount('bookmarks', 1);
    $this->assertDatabaseHas('bookmarks', ['user_id' => $this->user->id, 'commit_id' => $commit->id]);
});

it('may save their own commits in every moderation state', function (CommitStatus $status): void {
    $commit = Commit::factory()->for($this->user)->create(['status' => $status]);

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $commit));

    $response->assertOk()
        ->assertJsonPath('data.status', $status->value)
        ->assertJsonPath('data.is_saved', true);
})->with(CommitStatus::cases());

it('may not save a commit the user may not see', function (string $state): void {
    $commit = Commit::factory()->{$state}()->create();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $commit));

    $response->assertNotFound();

    $this->assertDatabaseCount('bookmarks', 0);
})->with(['pending', 'rejected']);

it('may not save a deleted commit', function (): void {
    $commit = Commit::factory()->for($this->user)->create();
    $commit->delete();

    $response = signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $commit));

    $response->assertNotFound();

    $this->assertDatabaseCount('bookmarks', 0);
});

it('unsaves a commit and answers with it unsaved', function (): void {
    $commit = Commit::factory()->create();
    Bookmark::factory()->create(['user_id' => $this->user->id, 'commit_id' => $commit->id]);
    $theirs = Bookmark::factory()->create(['commit_id' => $commit->id]);

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-bookmark.destroy', $commit));

    $response->assertOk()
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.is_saved', false);

    $this->assertDatabaseCount('bookmarks', 1);
    $this->assertModelExists($theirs);
});

it('unsaves a commit that was not saved', function (): void {
    $commit = Commit::factory()->create();

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-bookmark.destroy', $commit));

    $response->assertOk()
        ->assertJsonPath('data.is_saved', false);
});

it('may not unsave a commit the user may not see', function (): void {
    $commit = Commit::factory()->create();
    $bookmark = Bookmark::factory()->create(['user_id' => $this->user->id, 'commit_id' => $commit->id]);
    $commit->update(['status' => CommitStatus::Pending]);

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-bookmark.destroy', $commit));

    $response->assertNotFound()
        ->assertDontSee($commit->body);

    $this->assertModelExists($bookmark);
});

it('may not unsave a deleted commit', function (): void {
    $commit = Commit::factory()->create();
    $bookmark = Bookmark::factory()->create(['user_id' => $this->user->id, 'commit_id' => $commit->id]);
    $commit->delete();

    $response = signedApiRequest($this->keyPair, 'DELETE', route('api-bookmark.destroy', $commit));

    $response->assertNotFound();

    $this->assertModelExists($bookmark);
});

it('returns not found for unknown commits', function (string $method): void {
    $response = signedApiRequest($this->keyPair, $method, 'api/v1/commits/0199e3a4-0000-7000-8000-000000000000/bookmark');

    $response->assertNotFound();
})->with(['PUT', 'DELETE']);

it('lists the saved commits of the user, last saved first', function (): void {
    $author = User::factory()->create();
    $older = Commit::factory()->for($author)->create(['body' => 'It was DNS.', 'created_at' => now()->subYear()]);
    $newer = Commit::factory()->create();
    $image = CommitImage::factory()->for($older)->create(['width' => 640, 'height' => 480]);
    $reply = Commit::factory()->for($older, 'parent')->create();
    $older->stars()->attach($this->user);

    Bookmark::factory()->create(['user_id' => $this->user->id, 'commit_id' => $newer->id]);
    Bookmark::factory()->create(['user_id' => $this->user->id, 'commit_id' => $older->id]);

    $response = signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index'));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.reply_preview.data.*.id', [$reply->id])
        ->assertJsonPath('data.0.reply_preview.next_cursor', null)
        ->assertJsonPath('data.1.id', $newer->id)
        ->assertJsonPath('data.1.is_saved', true)
        ->assertJsonPath('meta.next_cursor', null)
        ->assertJsonPath('meta.per_page', 20);

    expect(collect($response->json('data.0'))->except('reply_preview')->all())->toBe([
        'id' => $older->id,
        'short_hash' => mb_substr(str_replace('-', '', $older->id), -7),
        'body' => 'It was DNS.',
        'parent_id' => null,
        'html_url' => route('commit.show', [$author, $older]),
        'created_at' => now()->subYear()->toIso8601ZuluString(),
        'stars_count' => 1,
        'replies_count' => 1,
        'link_preview' => null,
        'images' => [[
            'id' => $image->id,
            'width' => 640,
            'height' => 480,
            'description' => null,
            'url' => route('commit-image.show', $image),
        ]],
        'author' => [
            'id' => $author->id,
            'username' => $author->username,
            'name' => $author->name,
            'avatar_url' => $author->avatar_url,
            'bio' => $author->bio,
            'company' => $author->company,
            'location' => $author->location,
            'website' => $author->website,
            'github_url' => "https://github.com/{$author->username}",
            'html_url' => route('user.show', $author),
            'feed_url' => route('user-feed.show', $author),
            'created_at' => now()->toIso8601ZuluString(),
            'lane' => $author->lane(),
        ],
        'body_segments' => [['type' => 'text', 'text' => 'It was DNS.']],
        'preview_segments' => null,
        'mentions' => [],
        'status' => 'approved',
        'is_pinned' => false,
        'starred' => true,
        'is_saved' => true,
        'pasteis_count' => 0,
        'deleted_at' => null,
        'parent' => null,
        'quoted_commit_id' => null,
        'quoted_commit' => null,
        'poll' => null,
    ]);
});

it('never lists the saves of others', function (): void {
    $other = User::factory()->create();
    Bookmark::factory()->create(['user_id' => $other->id]);
    Bookmark::factory()->create(['user_id' => $other->id, 'commit_id' => Commit::factory()->for($this->user)]);

    $response = signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index', ['user_id' => $other->id]));

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

it('lists saved commits the user may still see', function (): void {
    $mine = Commit::factory()->for($this->user)->pending()->create();
    $hidden = Commit::factory()->create();
    $deleted = Commit::factory()->for($this->user)->create();
    Commit::factory()->for($deleted, 'parent')->create();

    foreach ([$mine, $hidden, $deleted] as $commit) {
        Bookmark::factory()->create(['user_id' => $this->user->id, 'commit_id' => $commit->id]);
    }

    $hidden->update(['status' => CommitStatus::Rejected]);
    $deleted->delete();

    $response = signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.status', 'pending');
});

it('lists saved replies with the parent the user may see', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $hiddenParent = Commit::factory()->pending()->create();
    $hiddenReply = Commit::factory()->for($hiddenParent, 'parent')->create(['created_at' => now()->subMinute()]);
    $deletedParent = Commit::factory()->create(['body' => 'Gone']);
    $orphanedReply = Commit::factory()->for($deletedParent, 'parent')->create(['created_at' => now()->subMinutes(2)]);
    $deletedParent->delete();

    foreach ([$orphanedReply, $hiddenReply, $reply] as $commit) {
        Bookmark::factory()->create(['user_id' => $this->user->id, 'commit_id' => $commit->id]);
    }

    $response = signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index'));

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $reply->id)
        ->assertJsonPath('data.0.parent.id', $parent->id)
        ->assertJsonPath('data.0.parent.author.id', $parent->user_id)
        ->assertJsonPath('data.1.id', $hiddenReply->id)
        ->assertJsonPath('data.1.parent', null)
        ->assertJsonPath('data.2.id', $orphanedReply->id)
        ->assertJsonPath('data.2.parent', [
            'id' => $deletedParent->id,
            'parent_id' => null,
            'deleted_at' => now()->toIso8601ZuluString(),
        ])
        ->assertDontSee('Gone');
});

it('lists saved commits 20 at a time with a cursor to the next page', function (): void {
    foreach (range(0, 21) as $index) {
        Bookmark::factory()->create([
            'user_id' => $this->user->id,
            'commit_id' => Commit::factory()->create(['body' => "Commit {$index}"]),
        ]);
    }

    $first = signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index'));

    $first->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('data.0.body', 'Commit 21')
        ->assertJsonPath('data.19.body', 'Commit 2');

    $second = signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index', ['cursor' => $first->json('meta.next_cursor')]));

    $second->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.body', 'Commit 1')
        ->assertJsonPath('data.1.body', 'Commit 0')
        ->assertJsonPath('meta.next_cursor', null);
});

it('does not query each saved commit or author', function (): void {
    signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index'))->assertOk();

    $queries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    Bookmark::factory()->count(2)->create(['user_id' => $this->user->id]);
    $few = $queries();

    Bookmark::factory()->count(8)->create(['user_id' => $this->user->id]);

    expect($queries())->toBe($few);
});

it('requires a signed request', function (string $method, string $uri): void {
    $commit = Commit::factory()->create();

    $response = $this->json($method, str_replace('{commit}', $commit->id, $uri));

    $response->assertUnauthorized();

    $this->assertDatabaseCount('bookmarks', 0);
})->with([
    'saved' => ['GET', 'api/v1/saved'],
    'save' => ['PUT', 'api/v1/commits/{commit}/bookmark'],
    'unsave' => ['DELETE', 'api/v1/commits/{commit}/bookmark'],
]);

it('may not save a commit across a block, and lists no saved commits across one', function (): void {
    $saved = Commit::factory()->create();
    Bookmark::factory()->create(['user_id' => $this->user->id, 'commit_id' => $saved->id]);
    $other = Commit::factory()->for($saved->user)->create();

    $saved->user->blocking()->attach($this->user);

    signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $other))->assertNotFound();
    signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('lists saved commits with the newest reply under each, as the web does', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();
    Bookmark::factory()->create(['user_id' => $this->user->id, 'commit_id' => $commit->id]);

    signedApiRequest($this->keyPair, 'GET', route('api-bookmark.index'))
        ->assertOk()
        ->assertJsonPath('data.0.reply_preview.data.*.id', [$reply->id]);
});

it('limits saves and unsaves to 60 a minute, like the web, with a code', function (): void {
    $commit = Commit::factory()->create();

    foreach (range(1, 60) as $attempt) {
        signedApiRequest($this->keyPair, $attempt % 2 === 0 ? 'DELETE' : 'PUT', route('api-bookmark.store', $commit))->assertOk();
    }

    signedApiRequest($this->keyPair, 'PUT', route('api-bookmark.store', $commit))
        ->assertTooManyRequests()
        ->assertExactJson(['message' => 'Too Many Attempts.', 'code' => 'rate_limited']);
});
