<?php

declare(strict_types=1);

use App\Contracts\Services\TranslationProvider;
use App\Enums\CommitStatus;
use App\Enums\Feature;
use App\Exceptions\CommitProcessingFailed;
use App\Models\Commit;
use App\Models\User;
use App\Services\CommitTranslations;
use App\Services\Features;
use Illuminate\Support\Facades\Exceptions;

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

it('requires a signed request', function (): void {
    $this->mock(TranslationProvider::class)->shouldNotReceive('translate');

    $this->postJson(route('api-commit-translation.store', Commit::factory()->create()), ['language' => 'en'])
        ->assertUnauthorized();
});
