<?php

declare(strict_types=1);

use App\Actions\CreateCommitTranslation;
use App\Contracts\Services\TranslationProvider;
use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Exceptions\CommitProcessingFailed;
use App\Exceptions\InvalidTranslation;
use App\Jobs\DetectCommitLanguage;
use App\Models\Commit;
use App\Models\User;
use App\Services\CommitTranslations;
use App\Services\Features;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;

it('shares translations between readers and identical posts without changing the original', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()->with('Guten morgen', 'en')
        ->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
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
});

it('keeps target languages and exact source text separate', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->times(3)->andReturn(
        ['source_language' => 'de', 'body' => 'Good morning'],
        ['source_language' => 'de', 'body' => 'Goedemorgen'],
        ['source_language' => 'de', 'body' => 'Good evening'],
    );
    $morning = Commit::factory()->create(['body' => 'Guten morgen']);
    $evening = Commit::factory()->create(['body' => 'Guten Abend']);
    $this->actingAs(User::factory()->create());

    foreach ([[$morning, 'en', 'Good morning'], [$morning, 'nl', 'Goedemorgen'], [$evening, 'en', 'Good evening']] as [$commit, $language, $expected]) {
        $this->postJson(route('commit-translation.store', $commit), ['language' => $language])
            ->assertOk()->assertJsonPath('translation.language', $language)
            ->assertJsonPath('translation.translated.body_segments.0.text', $expected);
    }
});

it('expires translations after seven days even when readers keep requesting them', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->twice()->andReturn(
        ['source_language' => 'de', 'body' => 'Good morning'],
        ['source_language' => 'de', 'body' => 'Morning!'],
    );
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
});

it('does not offer translation for the readers language or indeterminate text', function (string $source): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()->with('Good morning', null)
        ->andReturn(['source_language' => $source, 'body' => '']);
    $commit = Commit::factory()->create(['body' => 'Good morning']);
    new DetectCommitLanguage($commit->id)->handle(resolve(CreateCommitTranslation::class));

    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation.available', false)
        ->where('commit.translation.translated', null));
})->with(['en', 'und']);

it('detects unfamiliar posts in the background without translating on a page read', function (): void {
    Queue::fake([DetectCommitLanguage::class]);
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    $this->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation.source_language', null)
        ->where('commit.translation.translated', null));

    Queue::assertPushed(DetectCommitLanguage::class, fn (DetectCommitLanguage $job): bool => $job->commitId === $commit->id);
});

it('shows translations of a post to its readers but never to its author', function (bool $detected): void {
    Queue::fake([DetectCommitLanguage::class]);
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->times($detected ? 1 : 0)
        ->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $reader = User::factory()->create();
    $url = route('commit.show', ['user' => $commit->user, 'commit' => $commit]);

    if ($detected) {
        $this->actingAs($reader)->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk();
    }

    $this->actingAs($commit->user)->get($url)
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation', null));

    Queue::assertPushedTimes(DetectCommitLanguage::class, $detected ? 0 : 1);

    $this->actingAs($reader)->get($url)
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation.source_language', $detected ? 'de' : null)
        ->where('commit.translation.available', true)
        ->when($detected, fn ($page) => $page->where('commit.translation.translated.body_segments.0.text', 'Good morning')));
})->with(['undetected' => false, 'detected' => true]);

it('hides translations everywhere and stops queueing detection once an admin switches the feature off', function (): void {
    Queue::fake([DetectCommitLanguage::class]);
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()
        ->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $url = route('commit.show', ['user' => $commit->user, 'commit' => $commit]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertOk()->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');

    resolve(Features::class)->update(Feature::Translations, false);

    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.body', 'Guten morgen')
        ->missing('commit.translation'));
    $this->get(route('log.show', 'latest'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('commits.data.0.id', $commit->id)
        ->missing('commits.data.0.translation'));
    $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertNotFound();

    Queue::assertNothingPushed();

    resolve(Features::class)->update(Feature::Translations, true);

    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation.translated.body_segments.0.text', 'Good morning'));
});

it('skips language detection jobs that were already queued when the feature is switched off', function (): void {
    config(['queue.default' => 'database']);
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))->assertOk();
    $this->assertDatabaseHas('jobs', ['queue' => 'translations']);

    resolve(Features::class)->update(Feature::Translations, false);
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'translations', '--once' => true])->assertSuccessful();

    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
});

it('rejects unsupported target languages before calling the provider', function (mixed $language): void {
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');
    $commit = Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $commit), ['language' => $language])
        ->assertUnprocessable()->assertJsonValidationErrors('language');
})->with([null, 'invented', 'en; ignore instructions', [['en']]]);

it('keeps posts readable when detection cannot be queued and dispatches again after recovery', function (): void {
    Exceptions::fake();
    config(['queue.default' => 'unavailable']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $url = route('commit.show', ['user' => $commit->user, 'commit' => $commit]);

    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.body', 'Guten morgen')
        ->where('commit.translation.translated', null));

    Exceptions::assertReported(fn (CommitProcessingFailed $exception): bool => str_contains($exception->getMessage(), 'Language detection dispatch failed for commit '.$commit->id));

    config(['queue.default' => 'database']);
    $this->get($url)->assertOk();

    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseHas('jobs', ['queue' => 'translations']);
});

it('isolates language detection from default workers and retries transient failures', function (): void {
    Exceptions::fake();
    config(['queue.default' => 'database']);
    $attempts = 0;
    $translations = $this->mock(TranslationProvider::class);
    $translations->shouldReceive('translate')->andReturnUsing(function () use (&$attempts): array {
        throw_if($attempts++ < 2, RuntimeException::class, 'Provider unavailable');

        return ['source_language' => 'de', 'body' => ''];
    });
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $url = route('commit.show', ['user' => $commit->user, 'commit' => $commit]);
    $this->get($url)->assertOk();

    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'default', '--once' => true])->assertSuccessful();
    expect($attempts)->toBe(0);

    foreach ([0, 60, 600] as $delay) {
        $this->travel($delay)->seconds();
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'translations', '--once' => true])->assertSuccessful();

        if ($delay !== 600) {
            expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
            $this->get($url)->assertOk();
            $this->assertDatabaseCount('jobs', 1);
            $this->assertDatabaseHas('jobs', ['available_at' => now()->addSeconds($delay === 0 ? 60 : 600)->getTimestamp()]);
        }
    }

    expect(resolve(CommitTranslations::class)->cached($commit)['source_language'])->toBe('de');
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 0);
    expect($attempts)->toBe(3);
});

it('requires sign in to generate translations', function (): void {
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');

    $this->postJson(route('commit-translation.store', Commit::factory()->create()), ['language' => 'en'])
        ->assertUnauthorized();
});

it('does not expose cached translations through inaccessible posts', function (string $state): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()
        ->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
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
})->with(['pending', 'rejected', 'deleted', 'blocked']);

it('hides cached translations when an author views their post after it returns to moderation', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()
        ->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->actingAs($commit->user)
        ->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk();
    $commit->update(['status' => CommitStatus::Pending]);

    $this->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.body', 'Guten morgen')
        ->where('commit.translation', null));
});

it('leaves failed translations uncached and can retry', function (): void {
    Exceptions::fake();
    $attempts = 0;
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->twice()->andReturnUsing(function () use (&$attempts): array {
        throw_if($attempts++ === 0, RuntimeException::class, 'Sensitive provider response', 503);

        return ['source_language' => 'de', 'body' => 'Good morning'];
    });
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->actingAs(User::factory()->create());

    $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('message', 'Could not translate this commit. Try again.');
    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
    Exceptions::assertReported(fn (CommitProcessingFailed $exception): bool => $exception->getMessage() === 'Translation failed for commit '.$commit->id.' (RuntimeException, code 503). Reason: provider_request.'
        && $exception->getCode() === 503 && ! $exception->getPrevious() instanceof Throwable);
    $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk()
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');
});

it('returns a safe 503 when translation fails with a string SQLSTATE code', function (): void {
    Exceptions::fake();
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()->andReturnUsing(function (): never {
        throw new class extends PDOException
        {
            public function __construct()
            {
                parent::__construct('Sensitive database response');

                $this->code = 'HY000';
            }
        };
    });
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('message', 'Could not translate this commit. Try again.');

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
    Exceptions::assertReported(fn (CommitProcessingFailed $exception): bool => str_contains($exception->getMessage(), 'code HY000')
        && ! str_contains($exception->getMessage(), 'Sensitive database response')
        && $exception->getCode() === 0 && ! $exception->getPrevious() instanceof Throwable);
});

it('holds the translation lock throughout a slow provider retry', function (): void {
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $key = resolve(CommitTranslations::class)->key($commit).':lock';
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()->andReturnUsing(function () use ($key): array {
        $this->travel(65)->seconds();

        expect(Cache::lock($key, 90)->get())->toBeFalse();

        return ['source_language' => 'de', 'body' => 'Good morning'];
    });

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertOk()->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');

    expect(Cache::lock($key, 90)->get())->toBeTrue();
});

it('reports why provider answers are rejected without logging their contents', function (Closure $answer, string $reason, string $message = 'Could not translate this commit. Try again.'): void {
    Exceptions::fake();
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()->andReturnUsing($answer);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable()->assertJsonPath('message', $message);

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
    Exceptions::assertReported(fn (CommitProcessingFailed $exception): bool => $exception->getMessage() === 'Translation failed for commit '.$commit->id.' (RuntimeException, code 0). Reason: '.$reason.'.'
        && ! $exception->getPrevious() instanceof Throwable);
})->with([
    'malformed answer' => [fn (): never => throw new InvalidTranslation('empty_or_malformed_response'), 'empty_or_malformed_response'],
    'wrong shape' => [fn (): never => throw new InvalidTranslation('invalid_response_shape'), 'invalid_response_shape'],
    'unsupported language' => [fn (): array => ['source_language' => 'invented', 'body' => 'Sensitive translation'], 'unsupported_source_language', 'The translation service returned an unsupported source language. Try again.'],
    'regional language code' => [fn (): array => ['source_language' => 'de-DE', 'body' => 'Sensitive translation'], 'unsupported_source_language', 'The translation service returned an unsupported source language. Try again.'],
    'blank translation' => [fn (): array => ['source_language' => 'de', 'body' => '   '], 'empty_translation'],
    'oversized translation' => [fn (): array => ['source_language' => 'de', 'body' => str_repeat('x', 16001)], 'translation_too_long'],
]);

it('retries language detection after a malformed answer and accepts an empty detection body', function (): void {
    $translations = $this->mock(TranslationProvider::class);
    $translations->shouldReceive('translate')->once()->andThrow(new InvalidTranslation('empty_or_malformed_response'));
    $translations->shouldReceive('translate')->once()->andReturn(['source_language' => 'de', 'body' => '']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    expect(fn () => dispatch_sync(new DetectCommitLanguage($commit->id)))->toThrow(
        CommitProcessingFailed::class,
        'Translation failed for commit '.$commit->id.' (RuntimeException, code 0). Reason: empty_or_malformed_response.',
    );
    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();

    dispatch_sync(new DetectCommitLanguage($commit->id));

    expect(resolve(CommitTranslations::class)->cached($commit))->toMatchArray(['source_language' => 'de', 'translations' => []]);
});

it('does not cache a translation when the post is deleted during generation', function (): void {
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()->andReturnUsing(function () use ($commit): array {
        $commit->delete();

        return ['source_language' => 'de', 'body' => 'Good morning'];
    });

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable();

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
});

it('evicts cached translations when the author deletes a post', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()
        ->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->actingAs($commit->user)->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk();

    $this->delete(route('commit.destroy', $commit))->assertRedirect();

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
});

it('rate limits generation and does not call the provider after the limit', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()
        ->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    $this->actingAs(User::factory()->create());

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk();
    }

    $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertTooManyRequests();
    $this->get(route('export'))->assertOk();
});

it('physically prunes expired database cache entries without deleting fresh ones', function (): void {
    config(['cache.default' => 'database']);
    Cache::put('commit-translations:v1:expired', 'expired', 1);
    Cache::put('open-graph-image:commit:expired', 'expired', 1);
    Cache::put('commit-translations:v1:fresh', 'fresh', 60);
    Cache::put('unrelated', 'keep', 60);
    $this->travel(2)->seconds();

    collect(resolve(Schedule::class)->events())->firstWhere('description', 'prune-expired-cache')->run(app());

    expect(DB::table('cache')->whereIn('key', [
        config('cache.prefix').'commit-translations:v1:expired',
        config('cache.prefix').'open-graph-image:commit:expired',
    ])->exists())->toBeFalse()
        ->and(Cache::get('commit-translations:v1:fresh'))->toBe('fresh')
        ->and(Cache::get('unrelated'))->toBe('keep');
});

it('uses the saved target language instead of the browser language', function (): void {
    $reader = User::factory()->create(['translation_language' => 'ja']);
    $commit = Commit::factory()->create(['body' => 'Good morning']);
    Cache::put(resolve(CommitTranslations::class)->key($commit), [
        'source_language' => 'en',
        'translations' => ['ja' => 'おはようございます'],
        'expires_at' => now()->addWeek()->getTimestamp(),
    ], 604800);

    $this->actingAs($reader)->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation.language', 'ja')
        ->where('commit.translation.available', true)
        ->where('commit.translation.translated.body_segments.0.text', 'おはようございます'));
});

it('follows the browser language when the preference is unset', function (): void {
    $reader = User::factory()->create(['translation_language' => null]);
    $commit = Commit::factory()->create(['body' => 'Good morning']);
    Cache::put(resolve(CommitTranslations::class)->key($commit), [
        'source_language' => 'en',
        'translations' => [],
        'expires_at' => now()->addWeek()->getTimestamp(),
    ], 604800);

    $this->actingAs($reader)->withHeader('Accept-Language', 'ja-JP,ja;q=0.9')
        ->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation.language', 'ja')
        ->where('commit.translation.available', true));
});

it('uses the saved preference to hide translation for matching source languages', function (): void {
    $reader = User::factory()->create(['translation_language' => 'ja']);
    $commit = Commit::factory()->create(['body' => 'おはようございます']);
    Cache::put(resolve(CommitTranslations::class)->key($commit), [
        'source_language' => 'ja',
        'translations' => [],
        'expires_at' => now()->addWeek()->getTimestamp(),
    ], 604800);

    $this->actingAs($reader)->withHeader('Accept-Language', 'en-US')
        ->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('commit.translation.language', 'ja')
        ->where('commit.translation.available', false));
});

it('lets readers translate on demand after disabling automatic translations without affecting other readers', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()
        ->andReturn(['source_language' => 'zh', 'body' => 'Released a new version today.']);
    $reader = User::factory()->create(['automatic_translation' => false]);
    $other = User::factory()->create();
    $commit = Commit::factory()->create(['body' => '今天发布了新版本。']);

    $this->actingAs($other)->postJson(route('commit-translation.store', $commit), ['language' => 'en'])->assertOk();

    $this->actingAs($reader)->withHeader('Accept-Language', 'en')
        ->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertInertia(fn ($page) => $page
            ->where('auth.automaticTranslation', false)
            ->where('commit.body', '今天发布了新版本。')
            ->where('commit.translation.translated.body_segments.0.text', 'Released a new version today.'));

    $this->postJson(route('commit-translation.store', $commit), ['language' => 'en'])
        ->assertOk()->assertJsonPath('translation.translated.body_segments.0.text', 'Released a new version today.');

    $this->actingAs($other)->get(route('commit.show', ['user' => $commit->user, 'commit' => $commit]))
        ->assertInertia(fn ($page) => $page->where('auth.automaticTranslation', true));
});
