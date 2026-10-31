<?php

declare(strict_types=1);

use App\Jobs\DetectCommitLanguage;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitContinuationQuery;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('lists the next commits of the author thread as the API shows commits', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create(['created_at' => now()->subMinutes(5)]);
    $first = Commit::factory()->for($author)->for($commit, 'parent')->create(['created_at' => now()->subMinutes(4)]);
    $second = Commit::factory()->for($author)->for($first, 'parent')->create(['created_at' => now()->subMinutes(3)]);
    Commit::factory()->for($author)->for($commit, 'parent')->create(['created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($first, 'parent')->create(['created_at' => now()->subMinute()]);

    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commit))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$first->id, $second->id])
        ->assertJsonPath('data.0.author.handle', $author->handle)
        ->assertJsonPath('data.0.continuation_id', $second->id)
        ->assertJsonPath('data.0.replies_count', 1)
        ->assertJsonPath('data.1.continuation_id', null)
        ->assertJsonPath('meta.position', 1)
        ->assertJsonPath('meta.length', 2)
        ->assertJsonPath('meta.is_capped', false)
        ->assertJsonPath('meta.next_cursor', null)
        ->assertJsonPath('links.next', null);
});

it('lists the thread 10 commits at a time with a cursor to the next page', function (): void {
    $author = User::factory()->create();
    $commits = [Commit::factory()->for($author)->create()];

    foreach (range(1, CommitContinuationQuery::PER_PAGE + 2) as $ignored) {
        $commits[] = Commit::factory()->for($author)->for(end($commits), 'parent')->create();
    }

    $first = signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commits[0]));

    $first->assertOk()
        ->assertJsonPath('data.*.id', array_map(fn (Commit $commit): string => $commit->id, array_slice($commits, 1, 10)))
        ->assertJsonPath('data.9.continuation_id', $commits[11]->id)
        ->assertJsonPath('meta.position', 1)
        ->assertJsonPath('meta.length', 12);

    $second = signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', [$commits[0], 'cursor' => $first->json('meta.next_cursor')]));

    $second->assertOk()
        ->assertJsonPath('data.*.id', [$commits[11]->id, $commits[12]->id])
        ->assertJsonPath('meta.position', 11)
        ->assertJsonPath('meta.length', 2)
        ->assertJsonPath('meta.next_cursor', null);

    signedApiRequest($this->keyPair, 'GET', (string) $first->json('links.next'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$commits[11]->id, $commits[12]->id]);
});

it('does not query each commit of the thread, its author or its stars', function (): void {
    Queue::fake([DetectCommitLanguage::class]);
    $author = User::factory()->create();
    $commits = [Commit::factory()->for($author)->create()];

    $queries = function () use ($commits): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commits[0]))->assertOk();

        return count(DB::getQueryLog());
    };

    $create = function (int $count) use (&$commits, $author): void {
        foreach (range(1, $count) as $ignored) {
            $commits[] = $commit = Commit::factory()->for($author)->for(end($commits), 'parent')->create();
            $commit->stars()->attach([$this->user->id, User::factory()->create()->id]);
        }
    };

    $create(2);

    // The first signed request also syncs the user's keys with GitHub, and the first cards load the features...
    $queries();

    $few = $queries();

    $create(8);

    expect($queries())->toBe($few);
});

it('lists the first page for a cursor that is not one', function (mixed $cursor): void {
    $commit = Commit::factory()->create();
    $next = Commit::factory()->for($commit->user)->for($commit, 'parent')->create();

    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', [$commit, 'cursor' => $cursor]))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$next->id]);
})->with([
    'malformed' => ['not-a-cursor'],
    'array' => [['not-a-cursor']],
]);

it('returns not found for a cursor on something that is not a commit', function (): void {
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', [$commit, 'cursor' => new Cursor(['thread_id' => $commit->id, 'id' => 'not-a-uuid'])->encode()]))
        ->assertNotFound();
});

it('returns not found for a cursor from the thread of another commit', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit->user)->for($commit, 'parent')->create();
    $other = [Commit::factory()->create()];

    foreach (range(1, CommitContinuationQuery::PER_PAGE + 1) as $ignored) {
        $other[] = Commit::factory()->for($other[0]->user)->for(end($other), 'parent')->create();
    }

    $cursor = signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $other[0]))->json('meta.next_cursor');

    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', [$other[0], 'cursor' => $cursor]))->assertOk();
    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', [$commit, 'cursor' => $cursor]))->assertNotFound();
});

it('stops the thread at the first commit the user may not see', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create();
    $visible = Commit::factory()->for($author)->for($commit, 'parent')->create();
    $pending = Commit::factory()->for($author)->for($visible, 'parent')->pending()->create();
    Commit::factory()->for($author)->for($pending, 'parent')->create();

    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commit))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$visible->id])
        ->assertJsonPath('data.0.continuation_id', null)
        ->assertJsonPath('meta.length', 1);
});

it('returns not found for the thread of a commit the user may not see', function (): void {
    $commit = Commit::factory()->pending()->create();

    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commit))->assertNotFound();
});

it('returns not found for the thread of a commit across a block', function (): void {
    $commit = Commit::factory()->create();
    Commit::factory()->for($commit->user)->for($commit, 'parent')->create();
    $this->user->blocking()->attach($commit->user);

    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commit))->assertNotFound();
});

it('returns not found for a cursor on a commit the user may not see', function (): void {
    $commit = Commit::factory()->create();
    $hidden = Commit::factory()->pending()->create();

    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', [$commit, 'cursor' => new Cursor(['thread_id' => $commit->id, 'id' => $hidden->id])->encode()]))
        ->assertNotFound();
});

it('rejects requests that are not signed', function (): void {
    $this->getJson(route('api-commit-continuation.index', Commit::factory()->create()))->assertUnauthorized();
});
