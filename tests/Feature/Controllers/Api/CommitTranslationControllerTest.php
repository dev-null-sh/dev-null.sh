<?php

declare(strict_types=1);

use App\Contracts\Services\TranslationProvider;
use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Exceptions\CommitProcessingFailed;
use App\Jobs\DetectCommitLanguage;
use App\Models\Commit;
use App\Models\User;
use App\Services\CommitTranslations;
use App\Services\Features;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('translates a commit into the language asked for', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()->with('Guten morgen', 'en')
        ->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('translation.language', 'en')
        ->assertJsonPath('translation.source_name', 'German')
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');

    expect($commit->refresh()->body)->toBe('Guten morgen');
});

it('translates a pending commit for an admin', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()
        ->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
    config(['app.admins' => ['admin']]);
    $keyPair = apiKeyPairFor(User::factory()->create(['handle' => 'admin']));
    $commit = Commit::factory()->pending()->create(['body' => 'Guten morgen']);

    signedApiRequest($keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertOk()
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');
});

it('hides the commits the web does not translate for the user', function (string $state): void {
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    match ($state) {
        'pending' => $commit->update(['status' => CommitStatus::Pending]),
        'rejected' => $commit->update(['status' => CommitStatus::Rejected]),
        'blocked' => $this->user->blocking()->attach($commit->user),
        'empty' => $commit->update(['body' => '']),
        'switched off' => resolve(Features::class)->update(Feature::Translations, false),
    };

    signedApiRequest($this->keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertNotFound();
})->with(['pending', 'rejected', 'blocked', 'empty', 'switched off']);

it('rejects unsupported target languages before calling the provider', function (mixed $language): void {
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => $language])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('language');
})->with([null, 'invented', 'de-DE', [['en']]]);

it('answers a failed translation with a 503 the app can retry', function (): void {
    Exceptions::fake();
    $attempts = 0;
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->twice()->andReturnUsing(function () use (&$attempts): array {
        throw_if($attempts++ === 0, RuntimeException::class, 'Sensitive provider response', 503);

        return ['source_language' => 'de', 'body' => 'Good morning'];
    });
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['message' => 'Could not translate this commit. Try again.']);

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
    Exceptions::assertReported(fn (CommitProcessingFailed $exception): bool => $exception->getMessage() === 'Translation failed for commit '.$commit->id.' (RuntimeException, code 503). Reason: provider_request.');

    signedApiRequest($this->keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertOk()
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');
});

it('says when the provider answers in an unsupported source language', function (): void {
    Exceptions::fake();
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()
        ->andReturn(['source_language' => 'invented', 'body' => 'Sensitive translation']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    signedApiRequest($this->keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'The translation service returned an unsupported source language. Try again.']);

    Exceptions::assertReported(fn (CommitProcessingFailed $exception): bool => $exception->reason === 'unsupported_source_language');
});

it('reads a translation made by another reader without translating again', function (): void {
    $this->mock(TranslationProvider::class)->shouldReceive('translate')->once()
        ->with('Guten morgen', 'en')->andReturn(['source_language' => 'de', 'body' => 'Good morning']);
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    signedApiRequest($this->keyPair, 'POST', route('api-commit-translation.store', $commit), ['language' => 'en'])->assertOk();
    $reader = apiKeyPairFor(User::factory()->create());

    signedApiRequest($reader, 'GET', route('api-commit-translation.show', ['commit' => $commit, 'language' => 'en']))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('translation.language', 'en')
        ->assertJsonPath('translation.source_language', 'de')
        ->assertJsonPath('translation.source_name', 'German')
        ->assertJsonPath('translation.available', true)
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');

    expect($commit->refresh()->body)->toBe('Guten morgen');
});

it('does not generate a missing translation when reading', function (): void {
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    Queue::fake([DetectCommitLanguage::class]);

    signedApiRequest($this->keyPair, 'GET', route('api-commit-translation.show', ['commit' => $commit, 'language' => 'en']))
        ->assertOk()
        ->assertJsonPath('translation.language', 'en')
        ->assertJsonPath('translation.translated', null);

    expect(resolve(CommitTranslations::class)->cached($commit))->toBeNull();
    Queue::assertNotPushed(DetectCommitLanguage::class);
});

it('reads only the requested language from the shared translations', function (string $language, ?string $body, bool $available): void {
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);
    Cache::put(resolve(CommitTranslations::class)->key($commit), [
        'source_language' => 'de',
        'translations' => ['en' => 'Good morning', 'pt' => 'Bom dia'],
    ]);

    $response = signedApiRequest($this->keyPair, 'GET', route('api-commit-translation.show', ['commit' => $commit, 'language' => $language]));

    $response->assertOk()
        ->assertJsonPath('translation.language', $language)
        ->assertJsonPath('translation.available', $available);

    if ($body === null) {
        $response->assertJsonPath('translation.translated', null);
    } else {
        $response->assertJsonPath('translation.translated.body_segments.0.text', $body);
    }
})->with([
    'English' => ['en', 'Good morning', true],
    'Portuguese' => ['pt', 'Bom dia', true],
    'not translated yet' => ['ja', null, true],
    'original language' => ['de', null, false],
]);

it('hides translations the reader may not access', function (string $state): void {
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');
    $commit = Commit::factory()->create(['body' => 'Guten morgen']);

    match ($state) {
        'pending' => $commit->update(['status' => CommitStatus::Pending]),
        'rejected' => $commit->update(['status' => CommitStatus::Rejected]),
        'blocked' => $this->user->blocking()->attach($commit->user),
        'deleted' => $commit->delete(),
        'empty' => $commit->update(['body' => '']),
        'switched off' => resolve(Features::class)->update(Feature::Translations, false),
    };

    signedApiRequest($this->keyPair, 'GET', route('api-commit-translation.show', ['commit' => $commit, 'language' => 'en']))->assertNotFound();
})->with(['pending', 'rejected', 'blocked', 'deleted', 'empty', 'switched off']);

it('lets an admin read an existing pending translation', function (): void {
    config(['app.admins' => ['admin']]);
    $reader = apiKeyPairFor(User::factory()->create(['handle' => 'admin']));
    $commit = Commit::factory()->pending()->create(['body' => 'Guten morgen']);
    Cache::put(resolve(CommitTranslations::class)->key($commit), ['source_language' => 'de', 'translations' => ['en' => 'Good morning']]);

    signedApiRequest($reader, 'GET', route('api-commit-translation.show', ['commit' => $commit, 'language' => 'en']))
        ->assertOk()
        ->assertJsonPath('translation.translated.body_segments.0.text', 'Good morning');
});

it('validates the language before reading a translation', function (mixed $language): void {
    $commit = Commit::factory()->create();

    signedApiRequest($this->keyPair, 'GET', route('api-commit-translation.show', ['commit' => $commit, 'language' => $language]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('language');
})->with([null, 'invented', 'de-DE', [['en']]]);

it('requires a signed request to read a translation', function (): void {
    $commit = Commit::factory()->create();

    $this->getJson(route('api-commit-translation.show', ['commit' => $commit, 'language' => 'en']))->assertUnauthorized();
});

it('requires a signed request', function (): void {
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');

    $this->postJson(route('api-commit-translation.store', Commit::factory()->create()), ['language' => 'en'])
        ->assertUnauthorized();
});
