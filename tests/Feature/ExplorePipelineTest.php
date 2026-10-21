<?php

declare(strict_types=1);

use App\Actions\CreateCommitRestoration;
use App\Actions\CreateExploreFeedback;
use App\Actions\CreateExploreWork;
use App\Actions\CreateExport;
use App\Actions\CreateTomFriendship;
use App\Actions\DeleteCommit;
use App\Actions\DeleteExploreHiddenTopic;
use App\Actions\DeleteUser;
use App\Actions\UpdateCommitStatuses;
use App\Actions\UpdateExploreClassification;
use App\Actions\UpdateExplorePreference;
use App\Actions\UpdateExploreReport;
use App\Ai\Agents\ExploreClassifier;
use App\Ai\Agents\ExploreSummarizer;
use App\Ai\Tools\ExploreTopics;
use App\Enums\CommitStatus;
use App\Jobs\ProcessExplore;
use App\Jobs\SummarizeExplore;
use App\Models\Commit;
use App\Models\ExplorePreference;
use App\Models\ExploreTopic;
use App\Models\User;
use App\Queries\ExploreContentQuery;
use App\Queries\ExploreTopicsQuery;
use App\Services\ExploreBatch;
use App\Services\ExploreClassification;
use Illuminate\Bus\UniqueLock;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;

function exploreFixtureTopic(string $key = 'laravel', string $kind = 'subject'): ExploreTopic
{
    return ExploreTopic::query()->create([
        'kind' => $kind, 'key' => $key, 'title' => $key === 'laravel' ? 'Laravel' : str_replace('-', ' ', $key),
        'categories' => ['news', 'web-development'], 'summary' => 'Developers discuss '.$key.'.',
        'summary_generated_at' => now(),
    ]);
}

function exploreFixtureMembership(ExploreTopic $topic, Commit $commit, bool $source = false): void
{
    DB::table('explore_commit_topic')->insertOrIgnore(['topic_id' => $topic->id, 'commit_id' => $commit->id]);
    if ($source) {
        DB::table('explore_summary_sources')->insertOrIgnore(['topic_id' => $topic->id, 'commit_id' => $commit->id]);
    }
}

it('combines the three Laravel stories with standalone posts and counts overlapping membership once [EXP-02 EXP-03 EXP-07 EXP-08]', function (): void {
    $subject = exploreFixtureTopic();
    $security = exploreFixtureTopic('security');
    $stories = collect(['queue-dashboard', 'security-exploit', 'anniversary'])->map(fn (string $key): ExploreTopic => exploreFixtureTopic($key, 'story'));
    foreach ($stories as $story) {
        DB::table('explore_topic_subject')->insert(['story_id' => $story->id, 'subject_id' => $subject->id]);
    }

    DB::table('explore_topic_subject')->insert(['story_id' => $stories[1]->id, 'subject_id' => $security->id]);
    $posts = Commit::factory()->count(4)->create();
    foreach ($stories as $index => $story) {
        exploreFixtureMembership($story, $posts[$index], true);
        exploreFixtureMembership($subject, $posts[$index]);
    }

    exploreFixtureMembership($subject, $posts[3]);

    $results = resolve(ExploreTopicsQuery::class)->get();
    $laravel = $results->firstWhere('id', $subject->id);
    $exploit = $results->firstWhere('id', $stories[1]->id);

    expect($laravel['commits_count'])->toBe(4)
        ->and($laravel['contributors_count'])->toBe(4)
        ->and($exploit['subjects'])->toHaveCount(2)
        ->and(resolve(ExploreContentQuery::class)->members($subject)->count())->toBe(4)
        ->and($results->where('kind', 'story'))->toHaveCount(3);
});

it('changes topic ranking from current engagement without running classification [EXP-07]', function (): void {
    $first = exploreFixtureTopic('first');
    $second = exploreFixtureTopic('second');
    $author = User::factory()->create();
    $a = Commit::factory()->for($author)->create();
    $b = Commit::factory()->create();
    exploreFixtureMembership($first, $a);
    exploreFixtureMembership($second, $b);
    $a->update(['hot_score' => $a->hot_score + 2]);

    expect(resolve(ExploreTopicsQuery::class)->get()->first()['id'])->toBe($first->id);
    $b->update(['hot_score' => $b->hot_score + 4]);
    expect(resolve(ExploreTopicsQuery::class)->get()->first()['id'])->toBe($second->id);
});

it('counts each authors strongest contribution for ranking but all distinct posts for activity [EXP-07]', function (): void {
    $topic = exploreFixtureTopic();
    $author = User::factory()->create();
    $posts = Commit::factory()->count(3)->for($author)->create();
    foreach ($posts as $post) {
        exploreFixtureMembership($topic, $post);
    }

    $row = resolve(ExploreTopicsQuery::class)->get()->sole();

    expect($row['commits_count'])->toBe(3)->and($row['contributors_count'])->toBe(1)->and($row['score'])->toEqualWithDelta(1, 0.00001);
});

it('keeps pending rejected deleted bot and reply content out of discovery [EXP-04 EXP-09]', function (): void {
    $user = User::factory()->create();
    $public = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();
    Commit::factory()->create(['deleted_at' => now()]);
    Commit::factory()->for($public, 'parent')->create();
    Commit::factory()->for(User::factory()->state(['github_id' => CreateTomFriendship::GITHUB_ID]))->create();

    expect(resolve(ExploreContentQuery::class)->eligible($user)->pluck('id')->all())->toBe([$public->id]);
});

it('hides generated topics when any supporting source is muted or blocked [EXP-09]', function (string $relation): void {
    $viewer = User::factory()->create();
    $post = Commit::factory()->create();
    $topic = exploreFixtureTopic('private-derived-story', 'story');
    exploreFixtureMembership($topic, $post, true);
    $viewer->{$relation}()->attach($post->user);

    expect(resolve(ExploreTopicsQuery::class)->get($viewer))->toBeEmpty()
        ->and(resolve(ExploreTopicsQuery::class)->detail($topic, $viewer))->toBeNull();
})->with(['muting', 'blocking']);

it('invalidates generated text immediately when a source is deleted [EXP-03 EXP-09]', function (): void {
    $topic = exploreFixtureTopic('queue-story', 'story');
    $post = Commit::factory()->create();
    exploreFixtureMembership($topic, $post, true);

    resolve(DeleteCommit::class)->handle($post);

    expect($topic->fresh()->summary)->toBeNull()->and($topic->fresh()->summary_generated_at)->toBeNull()
        ->and(resolve(ExploreTopicsQuery::class)->get())->toBeEmpty();
});

it('waits for 500 commits or the 15 minute deadline and admits every eligible input once [EXP-04]', function (): void {
    Queue::fake([ProcessExplore::class, SummarizeExplore::class]);
    $author = User::factory()->create();
    $posts = Commit::factory()->count(500)->for($author)->create();
    $ids = $posts->map(fn (Commit $post): string => $post->id)->values()->all();
    resolve(CreateExploreWork::class)->handle(array_slice($ids, 0, 499));

    Queue::assertNotPushed(ProcessExplore::class);
    $this->artisan('explore:process')->assertSuccessful();
    Queue::assertNotPushed(ProcessExplore::class);
    $this->travel(15)->minutes();
    $this->artisan('explore:process')->assertSuccessful();
    Queue::assertPushed(ProcessExplore::class);
    Queue::assertPushed(SummarizeExplore::class);
    resolve(CreateExploreWork::class)->handle([$ids[499]]);
    resolve(CreateExploreWork::class)->handle([$ids[499]]);

    expect(DB::table('explore_classification_work')->count())->toBe(500);
});

it('groups related posts inside the oldest cohort and preserves full Unicode and code [EXP-05]', function (): void {
    $oldest = Commit::factory()->create(['body' => 'Laravel dashboard', 'embedding' => embeddingVector(0)]);
    $unrelated = Commit::factory()->create(['body' => 'Rust parser', 'embedding' => embeddingVector(1)]);
    $related = Commit::factory()->create(['body' => "Laravel 😀\n```php\n\$queue = 1;\n```", 'embedding' => embeddingVector(0)]);
    config(['explore.request_size' => 2]);

    $batch = resolve(ExploreBatch::class)->pack(collect([$oldest, $unrelated, $related]));
    $payload = json_decode($batch['prompt'], true);

    expect($batch['commits']->pluck('id')->all())->toBe([$oldest->id, $related->id])
        ->and($payload['commits'][1][2])->toBe($related->body)
        ->and($batch['prompt'])->not->toContain($oldest->id, $oldest->user_id)
        ->and(mb_strlen($batch['prompt']) + mb_strlen(new ExploreClassifier()->instructions()) + 4096)->toBeLessThanOrEqual(32768);
});

it('splits by bytes without truncating long bodies and processes missing embeddings [EXP-05]', function (): void {
    $posts = Commit::factory()->count(10)->create(['body' => str_repeat('😀', 1000)]);
    $remaining = $posts->toBase();
    $seen = [];
    while ($remaining->isNotEmpty()) {
        $batch = resolve(ExploreBatch::class)->pack($remaining);
        expect(mb_strlen($batch['prompt']) + mb_strlen(new ExploreClassifier()->instructions()) + 4096)->toBeLessThanOrEqual(32768);
        foreach (json_decode($batch['prompt'], true)['commits'] as $row) {
            expect($row[2])->toBe(str_repeat('😀', 1000));
        }

        $ids = $batch['commits']->pluck('id')->all();
        $seen = [...$seen, ...$ids];
        $remaining = $remaining->reject(fn (Commit $post): bool => in_array($post->id, $ids, true))->values();
    }

    expect(array_unique($seen))->toHaveCount(10);
});

it('accepts explicit no-topic outputs and rejects foreign references without dropping valid rows [EXP-02 EXP-05 EXP-06]', function (): void {
    $posts = Commit::factory()->count(2)->create()->toBase();
    $data = resolve(ExploreClassification::class)->decode(['topics' => [], 'assignments' => [
        ['ref' => 'c0', 'topics' => []], ['ref' => 'c1', 'topics' => ['invented']], ['ref' => 'c99', 'topics' => []],
    ]], $posts, []);

    expect($data['assignments'])->toBe([['ref' => 'c0', 'topics' => []]]);
});

it('rejects malformed classifications before persistence [EXP-05 EXP-06]', function (array $output): void {
    expect(fn () => resolve(ExploreClassification::class)->decode($output, collect(), []))->toThrow(ValidationException::class);
})->with([
    'missing arrays' => [[]],
    'duplicate assignments' => [['topics' => [], 'assignments' => [['ref' => 'c0', 'topics' => []], ['ref' => 'c0', 'topics' => []]]]],
    'invalid category' => [['topics' => [['ref' => 'n1', 'kind' => 'subject', 'key' => 'laravel', 'title' => 'Laravel', 'categories' => ['invented'], 'subjects' => [], 'aliases' => []]], 'assignments' => []]],
]);

it('classifies compact batches once and reuses subjects across requests [EXP-02 EXP-03 EXP-04 EXP-06]', function (): void {
    config(['ai.providers.opencode.key' => 'test', 'explore.request_size' => 1]);
    Queue::fake([SummarizeExplore::class, ProcessExplore::class]);
    $posts = Commit::factory()->count(2)->create();
    resolve(CreateExploreWork::class)->handle($posts->map(fn (Commit $post): string => $post->id)->all());
    ExploreClassifier::fake([
        ['topics' => [['ref' => 'n1', 'kind' => 'subject', 'key' => 'laravel', 'title' => 'Laravel', 'categories' => ['web-development'], 'subjects' => [], 'aliases' => ['#Laravel']]], 'assignments' => [['ref' => 'c0', 'topics' => ['n1']]]],
        ['topics' => [], 'assignments' => [['ref' => 'c0', 'topics' => ['t0']]]],
    ])->preventStrayPrompts();

    app()->call([new ProcessExplore(), 'handle']);

    expect(ExploreTopic::query()->count())->toBe(1)
        ->and(DB::table('explore_commit_topic')->count())->toBe(2)
        ->and(DB::table('explore_classification_work')->whereNotNull('completed_at')->count())->toBe(2);
    Queue::assertPushed(SummarizeExplore::class);
    ExploreClassifier::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, '"columns"'));
});

it('preserves completed rows and releases missing work after partial AI output [EXP-06]', function (): void {
    config(['ai.providers.opencode.key' => 'test']);
    Queue::fake([SummarizeExplore::class]);
    $posts = Commit::factory()->count(2)->create();
    resolve(CreateExploreWork::class)->handle($posts->map(fn (Commit $post): string => $post->id)->all());
    ExploreClassifier::fake([['topics' => [], 'assignments' => [['ref' => 'c0', 'topics' => []]]]])->preventStrayPrompts();

    expect(fn () => app()->call([new ProcessExplore(), 'handle']))->toThrow(RuntimeException::class, 'Explore classification failed.');
    expect(DB::table('explore_classification_work')->whereNotNull('completed_at')->count())->toBe(1)
        ->and(DB::table('explore_classification_work')->whereNotNull('claimed_at')->count())->toBe(0);
    Queue::assertPushed(SummarizeExplore::class);
});

it('generates a source-backed summary independently from classification thresholds [EXP-03 EXP-06]', function (): void {
    config(['ai.providers.opencode.key' => 'test']);
    $topic = exploreFixtureTopic('queue-story', 'story');
    $topic->update(['summary_dirty_at' => now()]);

    $post = Commit::factory()->create(['body' => 'Laravel announced a queue dashboard.']);
    exploreFixtureMembership($topic, $post);
    ExploreSummarizer::fake([['title' => 'Laravel queue dashboard announced', 'summary' => 'Community posts discuss the new dashboard.']])->preventStrayPrompts();

    app()->call([new SummarizeExplore(), 'handle']);

    expect($topic->fresh()->summary)->toBe('Community posts discuss the new dashboard.')
        ->and($topic->fresh()->summary_dirty_at)->toBeNull()
        ->and(DB::table('explore_summary_sources')->where('topic_id', $topic->id)->value('commit_id'))->toBe($post->id);
});

it('hides personally, reports to moderators, restores and suppresses durably [EXP-10]', function (): void {
    $user = User::factory()->create();
    $topic = exploreFixtureTopic();
    exploreFixtureMembership($topic, Commit::factory()->create());
    resolve(CreateExploreFeedback::class)->handle($user, $topic, 'not_interested');
    expect(DB::table('explore_reports')->count())->toBe(0)
        ->and(resolve(ExploreTopicsQuery::class)->get($user))->toBeEmpty();
    resolve(DeleteExploreHiddenTopic::class)->handle($user, $topic);
    expect(resolve(ExploreTopicsQuery::class)->get($user))->toHaveCount(1);
    resolve(CreateExploreFeedback::class)->handle($user, $topic, 'spam');
    resolve(CreateExploreFeedback::class)->handle($user, $topic, 'spam');

    expect(DB::table('explore_reports')->count())->toBe(1);
    resolve(UpdateExploreReport::class)->handle($topic, true);
    expect(resolve(ExploreTopicsQuery::class)->get())->toBeEmpty();
    resolve(UpdateExploreReport::class)->handle($topic, false);
    expect(resolve(ExploreTopicsQuery::class)->get())->toHaveCount(1);
});

it('encrypts and exports preferences and removes member-owned data with the account [EXP-09 EXP-10]', function (): void {
    $user = User::factory()->create();
    $topic = exploreFixtureTopic();
    resolve(UpdateExplorePreference::class)->handle($user, false);
    resolve(CreateExploreFeedback::class)->handle($user, $topic, 'spam');

    $export = resolve(CreateExport::class)->handle($user);

    expect(DB::table('explore_preferences')->value('preferences'))->not->toContain('personalized')
        ->and($export['explore']['preferences'])->toBe(['personalized' => false])
        ->and($export['explore']['hidden_topic_ids'])->toBe([$topic->id])
        ->and($export['explore']['reports'])->toHaveCount(1);
    resolve(DeleteUser::class)->handle($user);
    expect(ExplorePreference::query()->count())->toBe(0)
        ->and(DB::table('explore_hidden_topics')->count())->toBe(0)
        ->and(DB::table('explore_reports')->count())->toBe(0);
});

it('uses calendar-day windows across daylight saving changes [EXP-07 EXP-11]', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
    [$start, $end] = resolve(ExploreTopicsQuery::class)->window('today', 'Europe/London');

    expect($start->toIso8601String())->toBe('2026-10-24T23:00:00+00:00')
        ->and($end->setTimezone('Europe/London')->toDateString())->toBe('2026-10-25');
});

it('gives Tom the same news ranking with exact links, timezone and an honest result limit [EXP-11]', function (): void {
    $user = User::factory()->create(['timezone' => 'Europe/London']);
    $topic = exploreFixtureTopic('queue-dashboard', 'story');
    exploreFixtureMembership($topic, Commit::factory()->create(), true);
    $tool = new ExploreTopics($user);

    $result = json_decode($tool->handle(new Request(['category' => 'news', 'period' => 'today', 'limit' => 3])), true);

    expect($result['timezone'])->toBe('Europe/London')
        ->and($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['url'])->toBe($topic->url());
    $details = json_decode($tool->handle(new Request(['topic' => $topic->key])), true);
    expect($details['results'][0]['sources'])->toHaveCount(1);
    expect($tool->handle(new Request([])))->toContain('budget exhausted');
});

it('backfills only seven days in chronological eligibility order [EXP-04]', function (): void {
    Queue::fake([ProcessExplore::class, SummarizeExplore::class]);
    $older = Commit::factory()->create(['created_at' => now()->subDays(6)]);
    $newer = Commit::factory()->create(['created_at' => now()->subDay()]);
    Commit::factory()->create(['created_at' => now()->subDays(8)]);
    Commit::factory()->pending()->create();

    $this->artisan('explore:process', ['--backfill' => true])->assertSuccessful();
    $this->artisan('explore:process', ['--backfill' => true])->assertSuccessful();

    expect(resolve(ExploreContentQuery::class)->waiting()->pluck('commit_id')->all())->toBe([$older->id, $newer->id])
        ->and(DB::table('explore_classification_work')->where('commit_id', $older->id)->value('eligible_at'))->toBe($older->created_at->format('Y-m-d H:i:s'));
    Queue::assertPushed(ProcessExplore::class);
});

it('recovers expired claims while leaving active claims alone [EXP-04 EXP-06]', function (): void {
    $posts = Commit::factory()->count(3)->create();
    resolve(CreateExploreWork::class)->handle($posts->modelKeys());
    DB::table('explore_classification_work')->where('commit_id', $posts[0]->id)->update(['claimed_at' => now()->subMinutes(16)]);
    DB::table('explore_classification_work')->where('commit_id', $posts[1]->id)->update(['claimed_at' => now()->subMinutes(5)]);

    expect(resolve(ExploreContentQuery::class)->waiting()->pluck('commit_id')->all())->toBe([$posts[0]->id, $posts[2]->id]);
});

it('admits delayed approval once and invalidates text on rejection and undo [EXP-04 EXP-09]', function (): void {
    Queue::fake();
    $post = Commit::factory()->pending()->create(['created_at' => now()->subDays(2)]);
    resolve(CreateExploreWork::class)->handle([$post->id]);
    expect(DB::table('explore_classification_work')->count())->toBe(0);
    resolve(UpdateCommitStatuses::class)->handle([$post->id], CommitStatus::Approved);
    resolve(UpdateCommitStatuses::class)->handle([$post->id], CommitStatus::Approved);
    expect(DB::table('explore_classification_work')->count())->toBe(1)
        ->and(DB::table('explore_classification_work')->value('eligible_at'))->toBe(now()->format('Y-m-d H:i:s'));
    $topic = exploreFixtureTopic('restored-story', 'story');
    exploreFixtureMembership($topic, $post, true);
    resolve(UpdateCommitStatuses::class)->handle([$post->id], CommitStatus::Rejected);
    expect($topic->fresh()->summary)->toBeNull();
    resolve(UpdateCommitStatuses::class)->handle([$post->id], CommitStatus::Approved);
    resolve(DeleteCommit::class)->handle($post->fresh());

    $topic->update(['summary_dirty_at' => null]);
    resolve(CreateCommitRestoration::class)->handle($post->fresh());
    expect($topic->fresh()->summary_dirty_at)->not->toBeNull()
        ->and(resolve(ExploreContentQuery::class)->members($topic)->count())->toBe(1);
});

it('preserves valid summaries on invalid provider output and strips provider error details [EXP-06 EXP-12]', function (): void {
    config(['ai.providers.opencode.key' => 'test']);
    $topic = exploreFixtureTopic('provider-failure', 'story');
    $topic->update(['summary_dirty_at' => now()]);
    exploreFixtureMembership($topic, Commit::factory()->create(), true);
    ExploreSummarizer::fake([['title' => '', 'summary' => '']])->preventStrayPrompts();

    try {
        app()->call([new SummarizeExplore(), 'handle']);
        test()->fail('Invalid provider output must fail.');
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException->getMessage())->toBe('Explore summary failed.')
            ->and($runtimeException->getPrevious())->toBeNull();
    }

    expect($topic->fresh()->summary)->toBe('Developers discuss provider-failure.')
        ->and($topic->fresh()->summary_dirty_at)->not->toBeNull();
});

it('clears empty summaries and drains a full summary job independently [EXP-06]', function (): void {
    config(['ai.providers.opencode.key' => 'test', 'explore.summary_size' => 1]);
    Queue::fake([SummarizeExplore::class]);
    $topic = exploreFixtureTopic('empty-story', 'story');
    $topic->update(['summary_dirty_at' => now()]);
    ExploreSummarizer::fake()->preventStrayPrompts();

    app()->call([new SummarizeExplore(), 'handle']);

    expect($topic->fresh()->summary)->toBeNull()->and($topic->fresh()->summary_dirty_at)->toBeNull();
    ExploreSummarizer::assertNeverPrompted();
    Queue::assertPushed(SummarizeExplore::class);
});

it('boosts followed and starred topics only when personalization is requested and enabled [EXP-07]', function (): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $topic = exploreFixtureTopic();
    $post = Commit::factory()->for($author)->create();
    exploreFixtureMembership($topic, $post);
    $viewer->following()->attach($author);
    $viewer->starredCommits()->attach($post);
    $query = resolve(ExploreTopicsQuery::class);
    $publicScore = $query->get($viewer)->sole()['score'];
    expect($query->get($viewer, personalized: true)->sole()['score'])->toEqualWithDelta($publicScore * 1.25 * 1.25, 0.00001);
    resolve(UpdateExplorePreference::class)->handle($viewer, false);
    expect($query->get($viewer, personalized: true)->sole()['score'])->toEqualWithDelta($publicScore, 0.00001);
});

it('does not overwrite a summary when new classification arrives during generation [EXP-06]', function (): void {
    config(['ai.providers.opencode.key' => 'test']);
    $topic = exploreFixtureTopic('racing-story', 'story');
    $topic->update(['summary_dirty_at' => now()]);
    exploreFixtureMembership($topic, Commit::factory()->create(), true);
    ExploreSummarizer::fake(function () use ($topic): array {
        DB::table('explore_topics')->where('id', $topic->id)->increment('summary_revision');

        return ['title' => 'Outdated title', 'summary' => 'Outdated result'];
    })->preventStrayPrompts();

    app()->call([new SummarizeExplore(), 'handle']);

    expect($topic->fresh()->summary)->toBe('Developers discuss racing-story.')
        ->and($topic->fresh()->summary_dirty_at)->not->toBeNull();
});

it('sanitizes classification provider errors and releases claimed work [EXP-06 EXP-12]', function (): void {
    config(['ai.providers.opencode.key' => 'test']);
    Queue::fake([SummarizeExplore::class]);
    $post = Commit::factory()->create();
    resolve(CreateExploreWork::class)->handle([$post->id]);
    ExploreClassifier::fake(fn (): never => throw new RuntimeException('sensitive provider response'))->preventStrayPrompts();

    try {
        app()->call([new ProcessExplore(), 'handle']);
        test()->fail('Provider failure must fail.');
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException->getMessage())->toBe('Explore classification failed.')->and($runtimeException->getPrevious())->toBeNull();
    }

    expect(DB::table('explore_classification_work')->whereNull('claimed_at')->whereNull('completed_at')->count())->toBe(1);
});

it('persists discovered stories with shared normalized subject aliases across batches [EXP-02 EXP-03]', function (): void {
    $subject = exploreFixtureTopic();
    DB::table('explore_topic_aliases')->insert(['alias' => 'laravel-framework', 'topic_id' => $subject->id]);
    $post = Commit::factory()->create();
    resolve(CreateExploreWork::class)->handle([$post->id]);
    $raw = ['topics' => [
        ['ref' => 'n1', 'kind' => 'subject', 'key' => 'Laravel-framework', 'title' => 'Laravel', 'categories' => ['web-development'], 'subjects' => [], 'aliases' => ['#LARAVEL']],
        ['ref' => 'n2', 'kind' => 'story', 'key' => 'queue-launch', 'title' => 'Queue launch', 'categories' => ['news'], 'subjects' => ['n1'], 'aliases' => []],
    ], 'assignments' => [['ref' => 'c0', 'topics' => ['n2']]]];
    $posts = collect([$post]);
    $data = resolve(ExploreClassification::class)->decode($raw, $posts, []);
    resolve(UpdateExploreClassification::class)->handle($posts, [], $data);
    expect(ExploreTopic::query()->where('kind', 'subject')->count())->toBe(1)
        ->and(DB::table('explore_topic_subject')->value('subject_id'))->toBe($subject->id)
        ->and(DB::table('explore_topic_aliases')->where('alias', 'laravel')->value('topic_id'))->toBe($subject->id);
    $raw['topics'][1]['subjects'] = ['missing'];
    expect(resolve(ExploreClassification::class)->decode($raw, $posts, [])['assignments'])->toBeEmpty();
});

it('returns no work without credentials or when disabled and drains full cohorts immediately [EXP-04 EXP-06]', function (): void {
    ExploreClassifier::fake()->preventStrayPrompts();
    ExploreSummarizer::fake()->preventStrayPrompts();
    app()->call([new ProcessExplore(), 'handle']);
    app()->call([new SummarizeExplore(), 'handle']);
    config(['ai.providers.opencode.key' => 'test']);
    Queue::fake([ProcessExplore::class, SummarizeExplore::class]);
    app()->call([new ProcessExplore(), 'handle']);
    ExploreClassifier::assertNeverPrompted();
    ExploreSummarizer::assertNeverPrompted();
    config(['explore.cohort_size' => 1]);
    $posts = Commit::factory()->count(2)->create();
    resolve(CreateExploreWork::class)->handle($posts->modelKeys());
    ExploreClassifier::fake([['topics' => [], 'assignments' => [['ref' => 'c0', 'topics' => []]]]])->preventStrayPrompts();
    new UniqueLock(Cache::store())->release(new ProcessExplore());
    app()->call([new ProcessExplore(), 'handle']);
    expect(DB::table('explore_classification_work')->whereNotNull('completed_at')->count())->toBe(1);
    Queue::assertPushed(ProcessExplore::class, 2);
});

it('shrinks candidate catalogs and rejects an oversized single input without truncation [EXP-05]', function (): void {
    foreach (range(1, 6) as $index) {
        $topic = exploreFixtureTopic('candidate-'.$index);
        $topic->update(['title' => str_repeat('a', 240)]);
    }

    $post = Commit::factory()->create(['body' => 'Entire source']);
    config(['explore.prompt_bytes' => mb_strlen(new ExploreClassifier()->instructions(), '8bit') + 4096 + 180]);
    $batch = resolve(ExploreBatch::class)->pack(collect([$post]));
    expect($batch['catalog'])->toBeEmpty()->and($batch['prompt'])->toContain('Entire source');
    $post->body = str_repeat('😀', 2000);
    expect(fn () => resolve(ExploreBatch::class)->pack(collect([$post])))->toThrow(RuntimeException::class, 'Explore input exceeds');
});

it('bounds summary prompts by bytes and leaves oversized sources pending [EXP-05 EXP-06]', function (): void {
    config(['ai.providers.opencode.key' => 'test']);
    $topic = exploreFixtureTopic('large-summary', 'story');
    $topic->update(['summary_dirty_at' => now()]);
    foreach (Commit::factory()->count(2)->create(['body' => str_repeat('😀', 1000)]) as $post) {
        exploreFixtureMembership($topic, $post);
    }

    config(['explore.prompt_bytes' => mb_strlen(new ExploreSummarizer()->instructions(), '8bit') + 4096 + 4500]);
    ExploreSummarizer::fake([['title' => 'Large sources', 'summary' => 'A source-backed summary.']])->preventStrayPrompts();
    app()->call([new SummarizeExplore(), 'handle']);
    expect(DB::table('explore_summary_sources')->where('topic_id', $topic->id)->count())->toBe(1);
    $topic->update(['summary_dirty_at' => now()]);
    $topic->refresh()->update(['summary_dirty_at' => now()]);
    config(['explore.prompt_bytes' => 1]);
    expect(fn () => app()->call([new SummarizeExplore(), 'handle']))->toThrow(RuntimeException::class, 'Explore summary failed.');
    expect($topic->fresh()->summary_dirty_at)->not->toBeNull();
});

it('gives Tom subject rankings with normalized aliases and validates tool and provider contracts [EXP-11]', function (): void {
    $subject = exploreFixtureTopic();
    $story = exploreFixtureTopic('alias-story', 'story');
    DB::table('explore_topic_aliases')->insert(['alias' => 'laravel-framework', 'topic_id' => $subject->id]);
    DB::table('explore_topic_subject')->insert(['story_id' => $story->id, 'subject_id' => $subject->id]);
    exploreFixtureMembership($story, Commit::factory()->create(), true);
    $tool = new ExploreTopics();
    $result = json_decode($tool->handle(new Request(['subject' => '#Laravel Framework'])), true);
    expect($result['results'])->toHaveCount(2)->and($result['status'])->toBe('ready')
        ->and($tool->handle(new Request(['limit' => 11])))->toContain('Unsupported');
    $schema = new JsonSchemaTypeFactory();
    expect($tool->schema($schema))->toHaveKeys(['category', 'period', 'limit', 'subject', 'topic'])
        ->and($tool->description())->toContain('Two calls per reply')
        ->and(new ExploreClassifier()->providerOptions('opencode'))->toHaveKey('thinking')
        ->and(new ExploreSummarizer()->providerOptions('opencode'))->toHaveKey('thinking');
    $this->app->bind(ExploreTopicsQuery::class, fn (): never => throw new RuntimeException('sensitive database details'));
    expect(new ExploreTopics()->handle(new Request([])))->toContain('temporarily unavailable')->not->toContain('sensitive database');
});

it('claims disjoint chronological work across two PostgreSQL workers [EXP-04]', function (): void {
    $original = DB::getDefaultConnection();
    $connection = config('database.connections.'.$original);
    config(['database.connections.explore_worker_one' => $connection, 'database.connections.explore_worker_two' => $connection]);
    $author = User::factory()->connection('explore_worker_one')->create();
    $posts = Commit::factory()->connection('explore_worker_one')->count(2)->for($author)->create();
    $one = DB::connection('explore_worker_one');
    $two = DB::connection('explore_worker_two');
    $one->table('explore_classification_work')->insert($posts->map(fn (Commit $post): array => ['commit_id' => $post->id, 'eligible_at' => now()])->all());
    try {
        $one->beginTransaction();
        DB::setDefaultConnection('explore_worker_one');
        $first = resolve(ExploreContentQuery::class)->waiting()->lock('for update skip locked')->limit(1)->value('commit_id');
        $two->beginTransaction();
        DB::setDefaultConnection('explore_worker_two');
        $second = resolve(ExploreContentQuery::class)->waiting()->lock('for update skip locked')->limit(1)->value('commit_id');
        expect($first)->toBe($posts[0]->id)->and($second)->toBe($posts[1]->id);
        $two->table('explore_classification_work')->where('commit_id', $second)->update(['claimed_at' => now()]);
        $two->commit();
        $one->table('explore_classification_work')->where('commit_id', $first)->update(['claimed_at' => now()]);
        $one->commit();
        expect(resolve(ExploreContentQuery::class)->waiting()->count())->toBe(0);
    } finally {
        if ($one->transactionLevel() > 0) {
            $one->rollBack();
        }

        if ($two->transactionLevel() > 0) {
            $two->rollBack();
        }

        DB::setDefaultConnection($original);
        $one->table('commits')->whereIn('id', $posts->modelKeys())->delete();
        $one->table('users')->where('id', $author->id)->delete();
        DB::purge('explore_worker_one');
        DB::purge('explore_worker_two');
    }
});
