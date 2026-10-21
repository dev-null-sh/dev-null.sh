<?php

declare(strict_types=1);
use App\Ai\Agents\ExploreClassifier;
use App\Ai\Agents\ExploreSummarizer;
use App\Ai\Tools\ExploreTopics;
use App\Models\Commit;
use App\Models\ExploreTopic;
use App\Models\User;
use App\Services\TomToolDecision;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Laravel\Ai\Tools\Request;

// EXP-12: isolate only the temporary maintenance route in this test application.
// The production route collection and existing tests remain unchanged.
beforeEach(function (): void {
    $routes = new RouteCollection();
    foreach (Route::getRoutes() as $route) {
        if ($route->uri() !== '{any?}') {
            $routes->add($route);
        }
    }

    Route::setRoutes($routes);
});

function exploreHttpFixture(string $kind = 'story'): array
{
    $topic = ExploreTopic::query()->create([
        'kind' => $kind, 'key' => 'laravel-dashboard', 'title' => 'Laravel queue dashboard',
        'categories' => ['news', 'web-development'], 'summary' => 'Community posts discuss a new Laravel dashboard.',
        'summary_generated_at' => now(),
    ]);
    $post = Commit::factory()->create(['body' => 'Laravel announced a queue dashboard.']);
    DB::table('explore_commit_topic')->insert(['topic_id' => $topic->id, 'commit_id' => $post->id]);
    DB::table('explore_summary_sources')->insert(['topic_id' => $topic->id, 'commit_id' => $post->id]);

    return [$topic, $post];
}

it('renders public stories from saved classification without prompting AI [EXP-01 EXP-08]', function (): void {
    [$topic] = exploreHttpFixture();
    ExploreClassifier::fake()->preventStrayPrompts();
    ExploreSummarizer::fake()->preventStrayPrompts();

    $this->get(route('explore.index'))->assertOk()->assertInertia(fn ($page) => $page->component('explore/index')
        ->where('today_stories.0.id', $topic->id)->where('today_stories.0.commits_count', 1)
        ->where('tab', 'explore')->has('categories', 4));
    $this->get($topic->url())->assertOk()->assertInertia(fn ($page) => $page->component('explore/show')
        ->where('topic.title', $topic->title)->has('topic.sources', 1)->has('commits.data', 1));
    ExploreClassifier::assertNeverPrompted();
    ExploreSummarizer::assertNeverPrompted();
});

it('paginates top and latest topic feeds and validates category and order input [EXP-01 EXP-08]', function (): void {
    [$topic] = exploreHttpFixture('subject');
    $posts = Commit::factory()->count(20)->create(['created_at' => now()->subMinute()]);
    DB::table('explore_commit_topic')->insert($posts->map(fn (Commit $post): array => ['topic_id' => $topic->id, 'commit_id' => $post->id])->all());

    $response = $this->get($topic->url().'?order=latest')->assertOk()->assertInertia(fn ($page) => $page->has('commits.data', 20)->where('order', 'latest'));
    $cursor = $response->viewData('page')['props']['commits']['next_cursor'];
    $this->get($topic->url().'?order=latest&cursor='.urlencode($cursor))->assertOk()->assertInertia(fn ($page) => $page->has('commits.data', 1));
    $this->getJson(route('explore.index', ['tab' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('tab');
    $this->getJson($topic->url().'?order=unsafe')->assertUnprocessable()->assertJsonValidationErrors('order');
});

it('requires sign-in for personal writes and hides missing or excluded topics [EXP-09 EXP-10]', function (): void {
    [$topic, $post] = exploreHttpFixture();
    $this->post(route('explore-feedback.store', $topic), ['reason' => 'spam'])->assertRedirect(route('home'));
    $viewer = User::factory()->create();
    $viewer->blocking()->attach($post->user);
    $this->actingAs($viewer)->get($topic->url())->assertNotFound();
    $this->get('/explore/stories/missing')->assertNotFound();
    $this->post(route('explore-feedback.store', $topic), ['reason' => 'spam'])->assertForbidden();
    $this->assertDatabaseCount('explore_reports', 0);
});

it('validates feedback, hides topics, restores them and saves encrypted preferences [EXP-09 EXP-10]', function (): void {
    [$topic] = exploreHttpFixture();
    $viewer = User::factory()->create();
    $this->actingAs($viewer)->postJson(route('explore-feedback.store', $topic), ['reason' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->post(route('explore-feedback.store', $topic), ['reason' => 'spam'])->assertRedirect(route('explore.index'));
    $this->assertDatabaseHas('explore_reports', ['user_id' => $viewer->id, 'topic_id' => $topic->id, 'reason' => 'spam']);
    $this->get(route('explore-preference.show'))->assertOk()->assertInertia(fn ($page) => $page->component('explore/settings')->has('hidden', 1));
    $this->delete(route('explore-hidden.destroy', $topic))->assertRedirect();
    $this->assertDatabaseMissing('explore_hidden_topics', ['user_id' => $viewer->id, 'topic_id' => $topic->id]);
    $this->put(route('explore-preference.update'), ['personalized' => false])->assertRedirect();
    $this->get(route('explore-preference.show'))->assertOk()->assertInertia(fn ($page) => $page->where('personalized', false));
    $this->putJson(route('explore-preference.update'), [])->assertUnprocessable()->assertJsonValidationErrors('personalized');
});

it('prevents members moderating and lets administrators suppress restore or dismiss [EXP-10]', function (): void {
    [$topic] = exploreHttpFixture();
    $viewer = User::factory()->create();
    $this->actingAs($viewer)->post(route('explore-feedback.store', $topic), ['reason' => 'duplicate']);
    $this->get(route('explore-report.index'))->assertForbidden();
    $this->put(route('explore-report.update', $topic), ['suppressed' => true])->assertForbidden();
    config(['app.admins' => [$viewer->username]]);

    $this->get(route('explore-report.index'))->assertOk()->assertInertia(fn ($page) => $page->component('moderation/explore')->has('reports', 1));
    $this->put(route('explore-report.update', $topic), ['suppressed' => true])->assertRedirect();
    $this->get($topic->url())->assertNotFound();
    $this->assertDatabaseMissing('explore_reports', ['topic_id' => $topic->id, 'resolved_at' => null]);
    $this->put(route('explore-report.update', $topic), ['suppressed' => false])->assertRedirect();
    $this->get($topic->url())->assertNotFound();
    $this->delete(route('explore-hidden.destroy', $topic))->assertRedirect();
    $this->get($topic->url())->assertOk();
    $this->put(route('explore-report.update', $topic), [])->assertRedirect();
    $this->putJson(route('explore-report.update', $topic), ['suppressed' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('suppressed');
});

it('does not let one member restore another members hidden topics [EXP-09 EXP-10]', function (): void {
    [$topic] = exploreHttpFixture();
    $a = User::factory()->create();
    $b = User::factory()->create();
    DB::table('explore_hidden_topics')->insert(['user_id' => $a->id, 'topic_id' => $topic->id]);

    $this->actingAs($b)->delete(route('explore-hidden.destroy', $topic))->assertRedirect();

    $this->assertDatabaseHas('explore_hidden_topics', ['user_id' => $a->id, 'topic_id' => $topic->id]);
});

it('selects Explore for news and subject follow-ups with the requester context [EXP-11]', function (string $question): void {
    config(['ai.providers.openai.key' => 'test']);
    Http::fake(['api.openai.com/v1/decisions' => Http::response(['answers' => [
        ['name' => 'ExploreTopics', 'type' => 'predicate', 'probability' => 1],
    ]])]);
    $viewer = User::factory()->create(['timezone' => 'Asia/Tokyo']);
    [$topic] = exploreHttpFixture();

    $tools = resolve(TomToolDecision::class)->decide($question, [], $viewer);

    expect($tools)->toHaveCount(1)->and($tools->first())->toBeInstanceOf(ExploreTopics::class);
    $result = json_decode($tools->first()->handle(new Request(['category' => 'news', 'period' => 'today'])), true);
    expect($result['timezone'])->toBe('Asia/Tokyo')->and($result['results'][0]['id'])->toBe($topic->id);
    Http::assertSent(fn ($request): bool => collect($request['questions'])->contains('name', 'ExploreTopics'));
})->with(['@tom What are the top 3 news stories for today?', '@tom What is trending in Laravel?', '@tom Tell me more about the second story']);

it('separates related stories from Top and Latest commits while preserving subject stories [EXP-08]', function (): void {
    [$story] = exploreHttpFixture();
    [$subject] = exploreHttpFixture('subject');
    $related = ExploreTopic::query()->create([
        'kind' => 'story', 'key' => 'laravel-retries', 'title' => 'Laravel retry improvements',
        'categories' => ['news'], 'summary' => 'Developers discuss retry improvements.', 'summary_generated_at' => now(),
    ]);
    $post = Commit::factory()->create();
    DB::table('explore_commit_topic')->insert(['topic_id' => $related->id, 'commit_id' => $post->id]);
    DB::table('explore_summary_sources')->insert(['topic_id' => $related->id, 'commit_id' => $post->id]);
    DB::table('explore_topic_subject')->insert([
        ['story_id' => $story->id, 'subject_id' => $subject->id],
        ['story_id' => $related->id, 'subject_id' => $subject->id],
    ]);

    foreach (['top', 'latest'] as $order) {
        $this->get($story->url().'?order='.$order)->assertOk()->assertInertia(fn ($page) => $page
            ->where('order', $order)->has('commits.data', 1)->has('related', 0));
    }

    $this->get($story->url().'?order=related')->assertOk()->assertInertia(fn ($page) => $page
        ->where('order', 'related')->where('commits', null)->has('related', 1)->where('related.0.id', $related->id));
    $this->get($subject->url())->assertOk()->assertInertia(fn ($page) => $page
        ->has('related', 2)->has('commits.data', 3));
    $this->getJson($subject->url().'?order=related')->assertUnprocessable()->assertJsonValidationErrors('order');
});

it('returns an empty related tab when a story has no related discussions [EXP-08]', function (): void {
    [$story] = exploreHttpFixture();

    $this->get($story->url().'?order=related')->assertOk()->assertInertia(fn ($page) => $page
        ->where('order', 'related')->where('commits', null)->has('related', 0));
});

it('features the top subject per category using public ranking and viewer visibility [EXP-07 EXP-08 EXP-09]', function (array $leadingCategories, array $expectedKeys): void {
    $viewer = User::factory()->create();
    $subjects = collect();
    foreach ([
        ['leader', $leadingCategories, 10, []],
        ['followed-runner-up', ['ai'], 9.9, []],
        ['web', ['web-development'], 9.8, []],
        ['open', ['open-source'], 9.7, []],
        ['news', ['news'], 20, []],
        ['story', ['ai', 'web-development', 'open-source'], 20, ['kind' => 'story']],
        ['suppressed', ['ai', 'web-development', 'open-source'], 20, ['suppressed_at' => now()]],
        ['hidden', ['ai', 'web-development', 'open-source'], 20, []],
    ] as [$key, $categories, $score, $attributes]) {
        $topic = ExploreTopic::query()->create(array_merge([
            'kind' => 'subject', 'key' => $key, 'title' => $key, 'categories' => $categories,
            'summary' => 'Developers discuss '.$key, 'summary_generated_at' => now(),
        ], $attributes));
        $post = Commit::factory()->create(['hot_score' => now()->getTimestamp() / 10800 + $score]);
        DB::table('explore_commit_topic')->insert(['topic_id' => $topic->id, 'commit_id' => $post->id]);
        DB::table('explore_summary_sources')->insert(['topic_id' => $topic->id, 'commit_id' => $post->id]);
        $subjects->put($key, $topic);
        if ($key === 'followed-runner-up') {
            $viewer->following()->attach($post->user);
        }
    }

    DB::table('explore_hidden_topics')->insert(['user_id' => $viewer->id, 'topic_id' => $subjects['hidden']->id]);

    $this->actingAs($viewer)->get(route('explore.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('trending_subjects', 3)->missing('trending_stories')
        ->where('trending_subjects.0.category', 'ai')->where('trending_subjects.0.topic.id', $subjects[$expectedKeys[0]]->id)
        ->where('trending_subjects.1.category', 'web-development')->where('trending_subjects.1.topic.id', $subjects[$expectedKeys[1]]->id)
        ->where('trending_subjects.2.category', 'open-source')->where('trending_subjects.2.topic.id', $subjects[$expectedKeys[2]]->id));
})->with([
    'separate category leaders' => [['ai'], ['leader', 'web', 'open']],
    'one subject leads all categories' => [['ai', 'web-development', 'open-source'], ['leader', 'leader', 'leader']],
]);

it('omits categories with no eligible trending subject [EXP-08]', function (): void {
    [$subject] = exploreHttpFixture('subject');
    $subject->update(['summary' => null, 'summary_generated_at' => null]);

    $this->get(route('explore.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('trending_subjects', 1)->where('trending_subjects.0.category', 'web-development')
        ->where('trending_subjects.0.topic.id', $subject->id));
    $this->travel(25)->hours();
    $this->get(route('explore.index'))->assertOk()->assertInertia(fn ($page) => $page->has('trending_subjects', 0));
});

it('shows aggregate topic counts and a subject link for the trending category [EXP-02 EXP-07 EXP-08]', function (): void {
    [$subject] = exploreHttpFixture('subject');
    $author = User::factory()->create();
    $posts = Commit::factory()->count(2)->for($author)->create();
    foreach (['dashboard', 'retries'] as $key) {
        $story = ExploreTopic::query()->create([
            'kind' => 'story', 'key' => $key, 'title' => $key, 'categories' => ['web-development'],
            'summary' => 'Developers discuss '.$key, 'summary_generated_at' => now(),
        ]);
        DB::table('explore_topic_subject')->insert(['story_id' => $story->id, 'subject_id' => $subject->id]);
        DB::table('explore_commit_topic')->insert($posts->map(fn (Commit $post): array => [
            'topic_id' => $story->id, 'commit_id' => $post->id,
        ])->all());
    }

    DB::table('explore_commit_topic')->insert(['topic_id' => $subject->id, 'commit_id' => $posts[0]->id]);

    $this->get(route('explore.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('trending_subjects', 1)->where('trending_subjects.0.category', 'web-development')
        ->where('trending_subjects.0.topic.kind', 'subject')->where('trending_subjects.0.topic.url', $subject->url())
        ->where('trending_subjects.0.topic.commits_count', 3)->where('trending_subjects.0.topic.contributors_count', 2));
});
