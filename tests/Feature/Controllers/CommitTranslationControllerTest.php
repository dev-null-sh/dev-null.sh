<?php

declare(strict_types=1);

use App\Actions\CreateCommitTranslation;
use App\Ai\Agents\CommitTranslator;
use App\Enums\CommitStatus;
use App\Jobs\DetectCommitLanguage;
use App\Models\Commit;
use App\Models\User;
use App\Services\CommitTranslations;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function (): void {
    config(['ai.providers.opencode.key' => 'test-key']);
});

it('translates through DeepSeek on OpenCode without sending account metadata', function (): void {
    Http::fake([
        'opencode.ai/zen/v1/chat/completions' => Http::response([
            'choices' => [['finish_reason' => 'stop', 'message' => [
                'role' => 'assistant',
                'content' => '{"source_language":"de","body":"Good morning"}',
            ]]],
        ]),
    ]);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertOk()->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://opencode.ai/zen/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request['model'] === 'deepseek-v4.1-flash'
        && $request['thinking'] === ['type' => 'disabled']
        && collect($request['messages'])->where('role', 'user')->pluck('content')->all() === ['Guten morgen']);
});

it('shares translations between readers and identical posts without changing the original', function (): void {
    CommitTranslator::fake([
        ['source_language' => 'de', 'body' => 'Good morning'],
    ])->preventStrayPrompts();
    $first = Commit::factory()->create(['body' => 'Guten morgen']);
    $duplicate = Commit::factory()->create(['body' => 'Guten morgen']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $first), ['language' => 'en'])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('translation.source_name', 'German')
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');

    $this->actingAs(User::factory()->create())
        ->withHeader('Accept-Language', 'en-GB,en;q=0.9')
        ->get(route('commit.show', ['user' => $duplicate->user, 'commit' => $duplicate]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.body', 'Guten morgen')
        ->where('commit.translation.language', 'en')
        ->where('commit.translation.translated.body_segments.0.text', 'Good morning'));

    $this->postJson(route('commit-translation.store', $duplicate), ['language' => 'en'])
        ->assertOk()->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');

    expect($first->refresh()->body)->toBe('Guten morgen');
    CommitTranslator::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Guten morgen');
    CommitTranslator::assertPromptedTimes(1);
});

it('keeps target languages and exact source text separate', function (): void {
    CommitTranslator::fake([
        ['source_language' => 'de', 'body' => 'Good morning'],
        ['source_language' => 'de', 'body' => 'Goedemorgen'],
        ['source_language' => 'de', 'body' => 'Good evening'],
    ]);
    $morning = Commit::factory()->create(['body' => 'Guten morgen']);
    $evening = Commit::factory()->create(['body' => 'Guten Abend']);
    $this->actingAs(User::factory()->create());

    foreach ([[$morning, 'en', 'Good morning'], [$morning, 'nl', 'Goedemorgen'], [$evening, 'en', 'Good evening']] as [$commit, $language, $expected]) {
        $this->postJson(route('commit-translation.store', $commit), ['language' => $language])
            ->assertOk()->assertJsonPath('translation.language', $language)
            ->assertJsonPath('translation.translated.body_segments.0.text', $expected);
    }

    CommitTranslator::assertPromptedTimes(3);
});

it('expires translations after seven days even when readers keep requesting them', function (): void {
    CommitTranslator::fake([
        ['source_language' => 'de', 'body' => 'Good morning'],
        ['source_language' => 'de', 'body' => 'Morning!'],
    ]);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->actingAs(User::factory()->create());
    $url = route('commit-translation.store', $commit);

    $this->postJson($url, ['language' => 'en'])->assertOk();
    $this->travel(6)->days();
    $this->postJson($url, ['language' => 'en'])->assertOk()
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');
    $this->travel(2)->days();
    $this->postJson($url, ['language' => 'en'])->assertOk()
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Morning!');

    CommitTranslator::assertPromptedTimes(2);
});

it('does not offer translation for the readers language or indeterminate text', function (string $source): void {
    CommitTranslator::fake([['source_language' => $source, 'body' => '']]);
    $commit = Commit::factory()->create(['body' => 'Good morning']);
    new DetectCommitLanguage($commit->id)->handle(resolve(CreateCommitTranslation::class));

    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation.available', false)
        ->where('commit.translation.translated', null));

    CommitTranslator::assertPromptedTimes(1);
})->with(['en', 'und']);

it('detects unfamiliar posts in the background without translating on a page read', function (): void {
    Queue::fake([DetectCommitLanguage::class]);
    CommitTranslator::fake()->preventStrayPrompts();
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    $this->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation.source_language', null)
        ->where('commit.translation.translated', null));

    Queue::assertPushed(DetectCommitLanguage::class, fn (DetectCommitLanguage $job): bool => $job->commitId === $commit->id);
    CommitTranslator::assertNeverPrompted();
});

it('rejects unsupported target languages before calling the provider', function (mixed $language): void {
    CommitTranslator::fake()->preventStrayPrompts();
    $commit = Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $commit), ['language' => $language])
        ->assertUnprocessable()->assertJsonValidationErrors('language');

    CommitTranslator::assertNeverPrompted();
})->with([null, 'invented', 'en; ignore instructions', [['en']]]);

it('requires sign in to generate translations', function (): void {
    CommitTranslator::fake()->preventStrayPrompts();

    $this->postJson(route('commit-translation.store', Commit::factory()->create()), ['language' => 'en'])
        ->assertUnauthorized();

    CommitTranslator::assertNeverPrompted();
});

it('does not expose cached translations through inaccessible posts', function (string $state): void {
    CommitTranslator::fake([['source_language' => 'de', 'body' => 'Good morning']]);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $reader = User::factory()->create();
    $this->actingAs($reader)->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk();

    match ($state) {
        'pending' => $commit->update(['status' => CommitStatus::Pending]),
        'rejected' => $commit->update(['status' => CommitStatus::Rejected]),
        'deleted' => $commit->delete(),
        'blocked' => $reader->blocking()->attach($commit->user),
    };

    $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertNotFound();

    CommitTranslator::assertPromptedTimes(1);
})->with(['pending', 'rejected', 'deleted', 'blocked']);

it('hides cached translations when an author views their post after it returns to moderation', function (): void {
    CommitTranslator::fake([['source_language' => 'de', 'body' => 'Good morning']]);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->actingAs($commit->user)
        ->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk();
    $commit->update(['status' => CommitStatus::Pending]);

    $this->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.body', 'Guten morgen')
        ->where('commit.translation', null));

    CommitTranslator::assertPromptedTimes(1);
});

it('leaves failed translations uncached and can retry', function (): void {
    $attempts = 0;
    CommitTranslator::fake(function () use (&$attempts): array {
        throw_if($attempts++ === 0, RuntimeException::class, 'Provider unavailable');

        return ['source_language' => 'de', 'body' => 'Good morning'];
    });
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->actingAs(User::factory()->create());

    $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable()->assertJsonPath('message', 'Could not translate this commit. Try again.');
    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
    $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk()
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');
});

it('does not cache a translation when the post is deleted during generation', function (): void {
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    CommitTranslator::fake(function () use ($commit): array {
        $commit->delete();

        return ['source_language' => 'de', 'body' => 'Good morning'];
    });

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable();

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
});

it('evicts cached translations when the author deletes a post', function (): void {
    CommitTranslator::fake([['source_language' => 'de', 'body' => 'Good morning']]);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->actingAs($commit->user)->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk();

    $this->delete(route('commit.destroy', $commit))->assertRedirect();

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
});

it('rate limits generation and does not call the provider after the limit', function (): void {
    CommitTranslator::fake([['source_language' => 'de', 'body' => 'Good morning']]);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->actingAs(User::factory()->create());

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk();
    }

    $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertTooManyRequests();
    CommitTranslator::assertPromptedTimes(1);
});

it('physically prunes expired database translations without deleting fresh or unrelated cache entries', function (): void {
    config(['cache.default' => 'database']);
    Cache::put('commit-translations:v1:expired', 'expired', 1);
    Cache::put('commit-translations:v1:fresh', 'fresh', 60);
    Cache::put('unrelated', 'keep', 60);
    $this->travel(2)->seconds();

    collect(resolve(Schedule::class)->events())->firstWhere('description', 'prune-commit-translations')->run(app());

    expect(DB::table('cache')->where('key', config('cache.prefix').'commit-translations:v1:expired')->exists())->toBeFalse()
        ->and(Cache::get('commit-translations:v1:fresh'))->toBe('fresh')
        ->and(Cache::get('unrelated'))->toBe('keep');
});
