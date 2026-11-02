<?php

declare(strict_types=1);

use App\Dto\ThreadEngagement;
use App\Models\Commit;
use App\Models\Headline;
use App\Models\User;
use App\Queries\HotHeadlinesQuery;
use App\Services\TheAlgorithm;
use Illuminate\Database\Eloquent\Factories\Sequence;

it('aggregates shared headlines with their page identifiers without returning unused headlines', function (): void {
    $shared = Headline::factory()->create(['name' => 'Shared topic']);
    $other = Headline::factory()->create(['name' => 'Other topic']);
    Headline::factory()->create();
    Commit::factory()->create()->headlines()->attach([$shared->id, $other->id]);
    Commit::factory()->create()->headlines()->attach($shared);

    expect(resolve(HotHeadlinesQuery::class)->get())->toBe([
        ['id' => $shared->id, 'name' => 'Shared topic', 'commits_count' => 2, 'score' => 2.0],
        ['id' => $other->id, 'name' => 'Other topic', 'commits_count' => 1, 'score' => 1.0],
    ]);
});

it('boosts trending discussions logarithmically while preserving recent commit volume', function (): void {
    $broad = Headline::factory()->create(['name' => 'Broad topic']);
    $hot = Headline::factory()->create(['name' => 'Hot topic']);
    $older = Headline::factory()->create(['name' => 'Older hot topic']);
    $quiet = Headline::factory()->create(['name' => 'Quiet topic']);
    $algorithm = resolve(TheAlgorithm::class);
    $engagement = new ThreadEngagement(stargazers: 6, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0);
    Commit::factory()->count(4)->hasAttached($broad)->create();
    Commit::factory()->hasAttached($hot)->create(['hot_score' => $algorithm->score(now(), $engagement)]);
    Commit::factory()->hasAttached($older)->create([
        'created_at' => now()->subHours(6),
        'hot_score' => $algorithm->score(now()->subHours(6), $engagement),
    ]);
    Commit::factory()->hasAttached($quiet)->create(['created_at' => now()->subHour()]);

    $rows = resolve(HotHeadlinesQuery::class)->get();

    expect(array_column($rows, 'name'))->toBe(['Broad topic', 'Hot topic', 'Older hot topic', 'Quiet topic'])
        ->and(array_column($rows, 'commits_count'))->toBe([4, 1, 1, 1])
        ->and($rows[0]['score'])->toBe(4.0)
        ->and($rows[1]['score'])->toEqualWithDelta(3.0, 0.000002)
        ->and($rows[2]['score'])->toEqualWithDelta(2.0, 0.000002)
        ->and($rows[3]['score'])->toBe(1.0);
});

it('uses the rolling eight hours of posting time rather than headline link time', function (): void {
    $headline = Headline::factory()->create();
    Commit::factory()->create(['created_at' => now()->subHours(8)])->headlines()->attach($headline);
    Commit::factory()->create()->headlines()->attach($headline, ['created_at' => now()->subDay()]);
    Commit::factory()->create(['created_at' => now()->subHours(8)->subSecond()])->headlines()->attach($headline);
    Commit::factory()->create(['created_at' => now()->addSecond()])->headlines()->attach($headline);
    $query = resolve(HotHeadlinesQuery::class);

    expect($query->get())->toBe([['id' => $headline->id, 'name' => $headline->name, 'commits_count' => 2, 'score' => 2.0]]);

    $this->travel(1)->seconds();

    expect($query->get())->toBe([['id' => $headline->id, 'name' => $headline->name, 'commits_count' => 2, 'score' => 2.0]]);

    $this->travelTo(now()->addHours(8)->addSecond());

    expect($query->get())->toBe([]);
});

it('counts only approved undeleted top-level commits even for their author', function (): void {
    $viewer = User::factory()->create();
    $headline = Headline::factory()->create();
    $root = Commit::factory()->for($viewer)->create();
    $root->headlines()->attach($headline);
    Commit::factory()->for($viewer)->pending()->create()->headlines()->attach($headline);
    Commit::factory()->for($viewer)->rejected()->create()->headlines()->attach($headline);
    Commit::factory()->for($root, 'parent')->create()->headlines()->attach($headline);
    $deleted = Commit::factory()->create();
    $deleted->headlines()->attach($headline);
    $deleted->delete();

    expect(resolve(HotHeadlinesQuery::class)->get($viewer))->toBe([
        ['id' => $headline->id, 'name' => $headline->name, 'commits_count' => 1, 'score' => 1.0],
    ]);
});

it('excludes muted and blocked authors from both topic discovery and aggregation', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);
    $shared = Headline::factory()->create(['name' => 'Shared topic']);
    $hidden = Headline::factory()->create(['name' => 'Hidden topic']);
    Commit::factory()->create()->headlines()->attach($shared);

    foreach ([$muted, $blocked, $blocker] as $author) {
        Commit::factory()->for($author)->create()->headlines()->attach([$shared->id, $hidden->id]);
    }

    expect(resolve(HotHeadlinesQuery::class)->get($viewer))->toBe([
        ['id' => $shared->id, 'name' => 'Shared topic', 'commits_count' => 1, 'score' => 1.0],
    ]);
});

it('counts only each authors strongest commit per headline without rewarding repeated posts in ties', function (): void {
    $author = User::factory()->create();
    $repeated = Headline::factory()->create(['name' => 'Z repeated topic']);
    $other = Headline::factory()->create(['name' => 'A other topic']);
    $broad = Headline::factory()->create(['name' => 'B broad topic']);
    $algorithm = resolve(TheAlgorithm::class);
    $strongest = Commit::factory()->for($author)->create([
        'created_at' => now()->subHours(6),
        'hot_score' => $algorithm->score(now()->subHours(6), new ThreadEngagement(stargazers: 14, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0)),
    ]);
    $strongest->headlines()->attach([$repeated->id, $other->id]);
    Commit::factory()->for($author)->hasAttached($repeated)->create([
        'hot_score' => $algorithm->score(now(), new ThreadEngagement(stargazers: 2, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0)),
    ]);
    Commit::factory()->for($author)->hasAttached($repeated)->create();
    Commit::factory()->count(4)->hasAttached($broad)->create();

    $rows = resolve(HotHeadlinesQuery::class)->get();

    expect(array_column($rows, 'name'))->toBe(['B broad topic', 'A other topic', 'Z repeated topic'])
        ->and(array_column($rows, 'commits_count'))->toBe([4, 1, 3])
        ->and($rows[0]['score'])->toBe(4.0)
        ->and($rows[1]['score'])->toEqualWithDelta(3.0, 0.000002)
        ->and($rows[2]['score'])->toEqualWithDelta(3.0, 0.000002);
});

it('returns at most five headlines with stable ties resolved by author breadth and name', function (): void {
    $older = Headline::factory()->create(['name' => 'A older']);
    $newer = Headline::factory()->create(['name' => 'B newer']);
    $volume = Headline::factory()->create(['name' => 'C volume']);
    $hot = Headline::factory()->create(['name' => 'A hot']);
    Commit::factory()->hasAttached($older)->create(['created_at' => now()->subHour()]);
    Commit::factory()->hasAttached($newer)->create();
    Commit::factory()->count(2)->hasAttached($volume)->create(['created_at' => now()->subHours(2)]);
    Commit::factory()->hasAttached($hot)->create([
        'hot_score' => resolve(TheAlgorithm::class)->score(now(), new ThreadEngagement(stargazers: 2, quoters: 0, repliers: 0, repliersAnsweredByAuthor: 0)),
    ]);
    Commit::factory()->create()->headlines()->attach(
        Headline::factory()->count(7)->sequence(fn (Sequence $sequence): array => ['name' => 'D topic '.$sequence->index])->create(),
    );

    $query = resolve(HotHeadlinesQuery::class);
    $rows = $query->get();

    expect(array_column($rows, 'name'))->toBe([
        'C volume', 'A hot', 'A older', 'B newer', 'D topic 0',
    ])
        ->and($query->get())->toBe($rows);
});
