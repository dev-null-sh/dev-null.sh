<?php

declare(strict_types=1);

use App\Ai\Agents\ExploreClassifier;
use App\Ai\Agents\ExploreSummarizer;
use App\Models\Commit;
use App\Models\ExploreTopic;
use App\Queries\ExploreContentQuery;
use App\Queries\ExploreTopicsQuery;
use Database\Seeders\ExploreSeeder;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    // Isolate the existing maintenance response in this test application only.
    $routes = new RouteCollection();
    foreach (Route::getRoutes() as $route) {
        if ($route->uri() !== '{any?}') {
            $routes->add($route);
        }
    }

    Route::setRoutes($routes);
});

it('seeds seven substantial subjects with coherent source-backed stories without AI or queue work [EXP-02 EXP-03 EXP-08]', function (): void {
    Queue::fake();
    ExploreClassifier::fake()->preventStrayPrompts();
    ExploreSummarizer::fake()->preventStrayPrompts();

    $this->seed(ExploreSeeder::class);

    $this->assertDatabaseCount('explore_topics', 77);
    $this->assertDatabaseCount('users', 40);
    $this->assertDatabaseCount('commits', 1974);
    $this->assertDatabaseCount('explore_classification_work', 1694);
    expect(Commit::query()->topLevel()->count())->toBe(1694);
    expect(Commit::query()->whereNotNull('parent_id')->count())->toBe(280);
    expect(Commit::query()->whereNotNull('parent_id')->whereColumn('parent_id', '!=', 'root_id')->count())->toBe(70);
    expect(DB::table('explore_classification_work')->whereNull('completed_at')->count())->toBe(0);
    expect(Commit::query()->distinct()->count('body'))->toBe(1974);
    expect(Commit::query()->distinct()->count('hot_score'))->toBeGreaterThan(100);

    $content = resolve(ExploreContentQuery::class);
    foreach (ExploreTopic::query()->where('kind', 'subject')->get() as $subject) {
        expect(DB::table('explore_topic_subject')->where('subject_id', $subject->id)->count())->toBeGreaterThanOrEqual(10);
        expect($content->members($subject)->count())->toBeGreaterThanOrEqual(242);
    }

    foreach (ExploreTopic::query()->where('kind', 'story')->get() as $story) {
        expect($content->members($story)->count())->toBe(24);
        expect($content->members($story)->distinct()->count('user_id'))->toBe(24);
        $detail = resolve(ExploreTopicsQuery::class)->detail($story);
        expect($detail['summary'])->toStartWith('Demo story:');
        expect($detail['sources'])->toHaveCount(3);
        expect($detail['subjects'])->not->toBeEmpty();
    }

    expect(resolve(ExploreTopicsQuery::class)->get(period: 'today')->where('kind', 'story'))->toHaveCount(70);
    Queue::assertNothingPushed();
    ExploreClassifier::assertNeverPrompted();
    ExploreSummarizer::assertNeverPrompted();
});

it('keeps fixture IDs stable on rerun while preserving unrelated activity and topic suppression [EXP-03 EXP-10]', function (): void {
    $unrelated = Commit::factory()->create(['body' => 'My unrelated application work.']);
    $this->seed(ExploreSeeder::class);
    $ids = ExploreTopic::query()->orderBy('id')->pluck('id')->all();
    $topic = ExploreTopic::query()->where('kind', 'story')->firstOrFail();
    $topic->update(['suppressed_at' => now()]);
    DB::table('explore_hidden_topics')->insert(['user_id' => $unrelated->user_id, 'topic_id' => $topic->id]);
    $stars = DB::table('stars')->count();
    $follows = DB::table('follows')->count();
    $this->travel(2)->hours();

    $this->seed(ExploreSeeder::class);

    $this->assertDatabaseCount('explore_topics', 77);
    $this->assertDatabaseCount('commits', 1975);
    $this->assertDatabaseCount('users', 41);
    expect(ExploreTopic::query()->orderBy('id')->pluck('id')->all())->toBe($ids);
    expect($unrelated->fresh()->body)->toBe('My unrelated application work.');
    expect($topic->fresh()->suppressed_at)->not->toBeNull();
    expect(DB::table('stars')->count())->toBe($stars);
    expect(DB::table('follows')->count())->toBe($follows);
    $this->assertDatabaseHas('explore_hidden_topics', ['user_id' => $unrelated->user_id, 'topic_id' => $topic->id]);
    expect(resolve(ExploreTopicsQuery::class)->get()->where('kind', 'story'))->toHaveCount(69);
});

it('makes category pages subject feeds story feeds and source links browsable [EXP-01 EXP-08]', function (): void {
    $this->seed(ExploreSeeder::class);

    $this->get(route('explore.index'))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('explore/index')->has('today_stories', 3)->has('trending_subjects', 3)->has('recommended_commits', 20)
        ->missing('classification_updated_at')->missing('ranking_as_of')->missing('date')->missing('timezone'));
    foreach (['trending', 'news', 'ai', 'web-development', 'open-source'] as $tab) {
        $this->get(route('explore.index', ['tab' => $tab]))->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('tab', $tab)->where('topics.data', fn ($topics): bool => count($topics) > 0));
    }

    foreach (ExploreTopic::query()->where('kind', 'subject')->get() as $subject) {
        $this->get($subject->url())->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('explore/show')->where('topic.id', $subject->id)->has('commits.data', 20)->has('related', 10));
    }

    $story = ExploreTopic::query()->where('kind', 'story')->where('key', 'like', 'demo-%')->firstOrFail();
    foreach (['top', 'latest'] as $order) {
        $response = $this->get($story->url().'?order='.$order)->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('topic.commits_count', 24)->has('topic.sources', 3)->has('commits.data', 20)->where('order', $order));
        $next = $response->viewData('page')['props']['commits']['next_page_url'];
        expect($next)->not->toBeNull();
        $this->get($next)->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('commits.data', 4));
    }

    foreach (resolve(ExploreTopicsQuery::class)->detail($story)['sources'] as $source) {
        $this->get($source['url'])->assertOk()->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('commit/show'));
    }
});

it('refuses to place fictional Explore activity into a production environment', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => $this->artisan('db:seed', ['--class' => ExploreSeeder::class, '--force' => true])->run())->toThrow(RuntimeException::class, 'only available in local and testing');

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('explore_topics', 0);
});
