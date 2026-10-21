<?php

declare(strict_types=1);

use App\Ai\Agents\CommitTranslator;
use App\Enums\Feature;
use App\Exceptions\CommitProcessingFailed;
use App\Models\Commit;
use App\Models\User;
use App\Services\CommitTranslations;
use App\Services\Features;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['ai.providers.opencode.key' => 'test-key']);
});

it('refuses an unsigned translation', function (): void {
    $this->postJson(route('api-commit-translation.store', Commit::factory()->create()), ['language' => 'en'])
        ->assertUnauthorized();
});

it('translates a commit without changing the original, and a signed read includes the cache', function (): void {
    CommitTranslator::fake([['source_language' => 'de', 'body' => 'Good morning']])->preventStrayPrompts();
    $user = User::factory()->create(['translation_language' => 'en']);
    $keyPair = apiKeyPairFor($user);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    $response = signedApiRequest($keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en']);

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.id', $commit->id)
        ->assertJsonPath('data.body', 'Guten morgen')
        ->assertJsonPath('data.translation.language', 'en')
        ->assertJsonPath('data.translation.source_language', 'de')
        ->assertJsonPath('data.translation.source_name', 'German')
        ->assertJsonPath('data.translation.available', true)
        ->assertJsonPath('data.translation.translated.body_segments.0.text', 'Good morning');

    expect($commit->refresh()->body)->toBe('Guten morgen');

    signedApiRequest($keyPair, 'GET', route('api-commit.show', $commit))
        ->assertOk()
        ->assertJsonPath('data.translation.translated.body_segments.0.text', 'Good morning');

    signedApiRequest($keyPair, 'GET', route('api-log.show', 'latest'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $commit->id)
        ->assertJsonPath('data.0.translation.translated.body_segments.0.text', 'Good morning');

    $this->getJson(route('api-commit.show', $commit))
        ->assertOk()
        ->assertJsonPath('data.body', 'Guten morgen')
        ->assertJsonMissingPath('data.translation');

    CommitTranslator::assertPromptedTimes(1);
});

it('returns the requested language when it differs from the saved preference', function (): void {
    CommitTranslator::fake([['source_language' => 'de', 'body' => 'Good morning']])->preventStrayPrompts();
    $user = User::factory()->create(['translation_language' => 'ja']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.translation.language', 'en')
        ->assertJsonPath('data.translation.translated.body_segments.0.text', 'Good morning');
});

it('shows the author a null translation on their own pending commit', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->pending()->create(['body' => 'Guten morgen']);

    signedApiRequest(apiKeyPairFor($user), 'GET', route('api-commit.show', $commit))
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.translation', null);
});

it('does not translate a commit the user may not see, and does not call the provider', function (Closure $hide): void {
    CommitTranslator::fake()->preventStrayPrompts();
    $user = User::factory()->create();
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    $hide($user, $commit);

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertNotFound()
        ->assertExactJson(['message' => "This commit isn't available.", 'code' => 'commit_unavailable']);

    CommitTranslator::assertNeverPrompted();
})->with([
    'pending' => fn (User $user, Commit $commit) => $commit->update(['status' => 'pending']),
    'rejected' => fn (User $user, Commit $commit) => $commit->update(['status' => 'rejected']),
    'deleted' => fn (User $user, Commit $commit) => $commit->delete(),
    'missing' => function (User $user, Commit $commit): void {
        $commit->forceFill(['id' => (string) Str::uuid()])->syncOriginal();
    },
    'blocking' => fn (User $user, Commit $commit) => $user->blocking()->attach($commit->user),
    'blocked' => fn (User $user, Commit $commit) => $commit->user->blocking()->attach($user),
    'empty' => fn (User $user, Commit $commit) => $commit->update(['body' => '']),
]);

it('hides translations and refuses to generate them when the feature is off', function (): void {
    CommitTranslator::fake([['source_language' => 'de', 'body' => 'Good morning']])->preventStrayPrompts();
    $user = User::factory()->create(['translation_language' => 'en']);
    $keyPair = apiKeyPairFor($user);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    signedApiRequest($keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])->assertOk();

    resolve(Features::class)->update(Feature::Translations, false);

    signedApiRequest($keyPair, 'GET', route('api-commit.show', $commit))
        ->assertOk()
        ->assertJsonPath('data.body', 'Guten morgen')
        ->assertJsonMissingPath('data.translation');

    signedApiRequest($keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertNotFound()
        ->assertJsonPath('code', 'commit_unavailable');

    CommitTranslator::assertPromptedTimes(1);
});

it('rejects a language the translator is not allowed to receive', function (mixed $language): void {
    CommitTranslator::fake()->preventStrayPrompts();
    $user = User::factory()->create();
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-commit-translation.store', $commit), ['language' => $language])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('language');

    CommitTranslator::assertNeverPrompted();
})->with(['invented', 'ja-JP', 'JA']);

it('answers with a code when translation fails, caches nothing, and can be retried', function (): void {
    Exceptions::fake();
    $attempts = 0;
    CommitTranslator::fake(function () use (&$attempts): array {
        throw_if($attempts++ === 0, RuntimeException::class, 'Sensitive provider response', 503);

        return ['source_language' => 'de', 'body' => 'Good morning'];
    });
    $user = User::factory()->create();
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson([
            'message' => 'Could not translate this commit. Try again.',
            'code' => 'translation_failed',
        ])
        ->assertDontSee('Sensitive provider response');

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
    Exceptions::assertReported(fn (CommitProcessingFailed $exception): bool => str_contains($exception->getMessage(), 'Reason: provider_request.'));

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.translation.translated.body_segments.0.text', 'Good morning');
});

it('names an unsupported source language without returning the provider text', function (): void {
    Exceptions::fake();
    CommitTranslator::fake([['source_language' => 'invented', 'body' => 'Sensitive translation']])->preventStrayPrompts();
    $user = User::factory()->create();
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    signedApiRequest(apiKeyPairFor($user), 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable()
        ->assertExactJson([
            'message' => 'The translation service returned an unsupported source language. Try again.',
            'code' => 'translation_failed',
        ])
        ->assertDontSee('Sensitive translation');

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
});

it('stops at ten translations a minute and does not call the provider again', function (): void {
    CommitTranslator::fake([['source_language' => 'de', 'body' => 'Good morning']])->preventStrayPrompts();
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    foreach (range(1, 10) as $attempt) {
        signedApiRequest($keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])->assertOk();
    }

    signedApiRequest($keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', 60)
        ->assertExactJson([
            'message' => 'Too Many Attempts.',
            'code' => 'rate_limited',
        ]);

    CommitTranslator::assertPromptedTimes(1);
});

it('shares that limit with the web', function (): void {
    CommitTranslator::fake([['source_language' => 'de', 'body' => 'Good morning']])->preventStrayPrompts();
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    foreach (range(1, 10) as $attempt) {
        signedApiRequest($keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])->assertOk();
    }

    // The maintenance catch-all answers the web URL before its route, so the shared bucket is the named limiter both routes use.
    $request = Request::create('/commits/'.$commit->id.'/translation', 'POST', ['language' => 'en']);
    $request->setUserResolver(fn (): User => $user);

    try {
        app(ThrottleRequests::class)->handle($request, fn () => response('next'), 'translations');
        $this->fail('The web translation limit should already be spent.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(429)
            ->and($exception->getResponse()->getContent())->not->toContain('rate_limited');
    }

    signedApiRequest($keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');

    CommitTranslator::assertPromptedTimes(1);
});
