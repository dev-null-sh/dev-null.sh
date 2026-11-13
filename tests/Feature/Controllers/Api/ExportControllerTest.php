<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

it('downloads the user’s export as a private JSON file, as the web does', function (): void {
    $this->freezeSecond();
    $user = User::factory()->create(['handle' => 'zoe']);
    $commit = Commit::factory()->for($user)->create();
    Commit::factory()->for(User::factory()->create(['handle' => 'someoneelse']))->create();

    $response = signedApiRequest(apiKeyPairFor($user), 'GET', route('api-export.show'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="devnull-export-'.now()->format('Y-m-d-His').'.json"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $data = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store')
        ->and($data['schema_version'])->toBeInt()
        ->and($data['profile']['handle'])->toBe('zoe')
        ->and(array_column($data['commits'], 'id'))->toBe([$commit->id])
        ->and($response->streamedContent())->not->toContain('someoneelse');
});

it('shares the web’s budget of three exports an hour', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user)->get(route('export'))->assertOk();
    }

    signedApiRequest($keyPair, 'GET', route('api-export.show'))
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited')
        ->assertHeader('Retry-After');
    signedApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', route('api-export.show'))->assertOk();
});

it('also limits exports to five a minute per user independently of the hourly budget', function (): void {
    RateLimiter::for('exports', fn (): Limit => Limit::none());
    $keyPair = apiKeyPairFor(User::factory()->create());

    foreach (range(1, 5) as $attempt) {
        signedApiRequest($keyPair, 'GET', route('api-export.show'))->assertOk();
    }

    signedApiRequest($keyPair, 'GET', route('api-export.show'))
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited');

    $this->travel(1)->minute();

    signedApiRequest($keyPair, 'GET', route('api-export.show'))->assertOk();
});

it('keeps its minute limit apart from other routes’ minute limits', function (): void {
    $user = User::factory()->create(['pasteis_mode' => true]);
    $keyPair = apiKeyPairFor($user);
    $commit = Commit::factory()->create();

    foreach (range(1, 5) as $attempt) {
        signedApiRequest($keyPair, 'POST', route('api-commit-pastel.store', $commit))->assertOk();
    }

    signedApiRequest($keyPair, 'GET', route('api-export.show'))->assertOk();
});

it('requires a signed request', function (): void {
    $this->getJson(route('api-export.show'))->assertUnauthorized();
});
