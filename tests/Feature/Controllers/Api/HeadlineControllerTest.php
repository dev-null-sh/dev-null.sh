<?php

declare(strict_types=1);

use App\Jobs\DetectCommitLanguage;
use App\Models\Commit;
use App\Models\Headline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('lists the hot topics with their fresh recaps, as the web aside does', function (): void {
    $indexing = Headline::factory()->create(['name' => 'Database indexing', 'summary' => 'Developers compare indexing strategies.', 'summary_created_at' => now()->subHour()]);
    $releases = Headline::factory()->create(['name' => 'Release trains', 'summary' => 'An old recap.', 'summary_created_at' => now()->subHours(3)]);
    Commit::factory()->count(3)->hasAttached($indexing)->create();
    Commit::factory()->count(2)->hasAttached($releases)->create();
    Commit::factory()->hasAttached(Headline::factory()->create())->create();

    signedApiRequest($this->keyPair, 'GET', route('api-headline.index'))
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $indexing->id, 'name' => 'Database indexing', 'summary' => 'Developers compare indexing strategies.'],
            ['id' => $releases->id, 'name' => 'Release trains', 'summary' => null],
        ]]);
});

it('leaves out hot topics carried only by muted or blocked authors', function (string $relationship): void {
    $headline = Headline::factory()->create();
    [$first, $second] = User::factory()->count(2)->create();
    $this->user->{$relationship}()->attach([$first->id, $second->id]);
    Commit::factory()->for($first)->hasAttached($headline)->create();
    Commit::factory()->for($second)->hasAttached($headline)->create();

    signedApiRequest($this->keyPair, 'GET', route('api-headline.index'))
        ->assertOk()
        ->assertExactJson(['data' => []]);
})->with(['muting', 'blocking']);

it('shows a headline with its fresh recap and only the visible commits from the past week, newest first', function (): void {
    $headline = Headline::factory()->create(['name' => 'Database indexing', 'summary' => 'Developers compare indexing strategies.', 'summary_created_at' => now()->subHour()]);
    $oldest = Commit::factory()->hasAttached($headline)->create(['created_at' => now()->subWeek()]);
    $newest = Commit::factory()->hasAttached($headline)->create(['created_at' => now()->subHour()]);
    $middle = Commit::factory()->hasAttached($headline)->create(['created_at' => now()->subDays(2)]);
    Commit::factory()->hasAttached($headline)->create(['created_at' => now()->subWeek()->subSecond()]);
    Commit::factory()->hasAttached(Headline::factory()->create())->create();
    Commit::factory()->pending()->hasAttached($headline)->create();
    Commit::factory()->rejected()->hasAttached($headline)->create();
    Commit::factory()->hasAttached($headline)->create(['deleted_at' => now()]);
    Commit::factory()->for($newest, 'parent')->create();
    Commit::factory()->for($middle->user)->for($middle, 'parent')->create();

    signedApiRequest($this->keyPair, 'GET', route('api-headline.show', $headline))
        ->assertOk()
        ->assertJsonPath('name', 'Database indexing')
        ->assertJsonPath('summary', 'Developers compare indexing strategies.')
        ->assertJsonPath('data.*.id', [$newest->id, $middle->id, $oldest->id])
        ->assertJsonPath('data.0.replies_count', 1)
        ->assertJsonPath('data.1.replies_count', 0);
});

it('hides an expired recap without hiding the commits', function (): void {
    $headline = Headline::factory()->create(['summary' => 'An old recap.', 'summary_created_at' => now()->subHours(2)]);
    $commit = Commit::factory()->hasAttached($headline)->create();

    signedApiRequest($this->keyPair, 'GET', route('api-headline.show', $headline))
        ->assertOk()
        ->assertJsonPath('summary', null)
        ->assertJsonPath('data.*.id', [$commit->id]);
});

it('does not repeat muted or blocked authors through the recap', function (string $relationship): void {
    $headline = Headline::factory()->create(['summary' => 'A recap with hidden contributions.', 'summary_created_at' => now()]);
    $hidden = User::factory()->create();
    $this->user->{$relationship}()->attach($hidden);
    Commit::factory()->for($hidden)->hasAttached($headline)->create();
    $visible = Commit::factory()->hasAttached($headline)->create();

    signedApiRequest($this->keyPair, 'GET', route('api-headline.show', $headline))
        ->assertOk()
        ->assertJsonPath('summary', null)
        ->assertJsonPath('data.*.id', [$visible->id]);
})->with(['muting', 'blocking']);

it('reads a headline without the query count growing with its commits', function (): void {
    Queue::fake([DetectCommitLanguage::class]);
    $short = Headline::factory()->create();
    $long = Headline::factory()->create();
    Commit::factory()->hasAttached($short)->create();
    Commit::factory()->count(4)->hasAttached($long)->create()
        ->each(fn (Commit $commit): Commit => Commit::factory()->for($commit, 'parent')->create());

    $queries = function (Headline $headline): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        signedApiRequest($this->keyPair, 'GET', route('api-headline.show', $headline))->assertOk();

        return count(DB::getQueryLog());
    };

    // The first signed request also syncs the user's keys with GitHub...
    $queries($short);

    expect($queries($long))->toBe($queries($short));
});

it('returns not found for an unknown headline', function (): void {
    signedApiRequest($this->keyPair, 'GET', route('api-headline.show', Str::uuid()->toString()))->assertNotFound();
});

it('rejects requests that are not signed', function (string $route): void {
    $this->getJson($route === 'api-headline.index' ? route($route) : route($route, Headline::factory()->create()))->assertUnauthorized();
})->with(['api-headline.index', 'api-headline.show']);
