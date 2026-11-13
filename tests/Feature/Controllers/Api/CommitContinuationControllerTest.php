<?php

declare(strict_types=1);

use App\Jobs\DetectCommitLanguage;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitContinuationQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

/**
 * @return list<Commit>
 */
function selfThread(int $replies): array
{
    $author = User::factory()->create();
    $commits = [Commit::factory()->for($author)->create()];

    foreach (range(1, $replies) as $ignored) {
        $commits[] = Commit::factory()->for(end($commits), 'parent')->for($author)->create();
    }

    return $commits;
}

it('lists the author thread below a commit in order, as the web does', function (): void {
    $commits = selfThread(3);
    Commit::factory()->for($commits[0], 'parent')->create();

    $response = signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commits[0]));

    $response->assertOk()
        ->assertJsonPath('data.*.id', [$commits[1]->id, $commits[2]->id, $commits[3]->id])
        ->assertJsonPath('data.0.author.handle', $commits[0]->user->handle)
        ->assertJsonPath('data.0.status', 'approved')
        ->assertJsonPath('position', 1)
        ->assertJsonPath('length', 3)
        ->assertJsonPath('is_capped', false)
        ->assertJsonPath('next_id', null);
});

it('lists the thread a page at a time, going on from the last commit shown', function (): void {
    $commits = selfThread(CommitContinuationQuery::PER_PAGE + 2);

    $first = signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commits[0]));

    $first->assertOk()
        ->assertJsonCount(CommitContinuationQuery::PER_PAGE, 'data')
        ->assertJsonPath('next_id', $commits[CommitContinuationQuery::PER_PAGE + 1]->id);

    $second = signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $first->json('data.9.id')));

    $second->assertOk()
        ->assertJsonPath('data.*.id', [$commits[11]->id, $commits[12]->id])
        ->assertJsonPath('position', CommitContinuationQuery::PER_PAGE + 1)
        ->assertJsonPath('next_id', null);
});

it('does not query each commit of the thread', function (): void {
    Queue::fake([DetectCommitLanguage::class]);
    $short = selfThread(2);
    $long = selfThread(8);

    $queries = function (Commit $commit): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commit))->assertOk();

        return count(DB::getQueryLog());
    };

    // The first signed request also syncs the user's keys with GitHub...
    $queries($short[0]);

    expect($queries($long[0]))->toBe($queries($short[0]));
});

it('returns not found for a commit the user may not see', function (): void {
    $commit = Commit::factory()->pending()->create();

    signedApiRequest($this->keyPair, 'GET', route('api-commit-continuation.index', $commit))->assertNotFound();
});

it('rejects requests that are not signed', function (): void {
    $this->getJson(route('api-commit-continuation.index', Commit::factory()->create()))->assertUnauthorized();
});
