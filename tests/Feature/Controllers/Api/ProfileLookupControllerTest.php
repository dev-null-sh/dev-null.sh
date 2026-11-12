<?php

declare(strict_types=1);

use App\Jobs\LookUpGitHubProfile;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->member = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->member);
});

it('says where looking a handle up on GitHub stands', function (Closure $setUp, string $status, bool $resumes): void {
    $setUp();

    signedApiRequest($this->keyPair, 'GET', route('api-profile-lookup.show', 'Octocat'))
        ->assertOk()
        ->assertJsonPath('status', $status)
        ->assertJsonPath('resumes_at', fn (?string $resumesAt): bool => $resumes === ($resumesAt !== null));
})->with([
    'never looked up' => [fn (): null => null, 'idle', false],
    'being looked up' => [fn () => Cache::put('github-profiles:looking-up:octocat', true, 30), 'pending', false],
    'no GitHub account' => [fn () => Cache::put('github-profiles:not-found:octocat', 'missing', 60), 'missing', false],
    'waiting for the rate limit' => [fn () => Cache::put('github-organizations:paused', now()->addMinutes(30)->getTimestamp(), 60), 'paused', true],
]);

it('answers not found for handles GitHub could not have, or of people who deleted their account', function (string $handle): void {
    Cache::put('github-profiles:not-found:gone', 'deleted', 60);

    signedApiRequest($this->keyPair, 'GET', route('api-profile-lookup.show', $handle))->assertNotFound();
})->with(['an underscore' => 'oct_cat', 'a trailing dash' => 'octocat-', 'too long' => str_repeat('a', 40), 'deleted' => 'gone']);

it('looks a handle up, which then stands pending', function (): void {
    Queue::fake([LookUpGitHubProfile::class]);

    signedApiRequest($this->keyPair, 'POST', route('api-profile-lookup.store'), ['handle' => 'Octocat'])->assertAccepted();

    Queue::assertPushed(LookUpGitHubProfile::class, fn (LookUpGitHubProfile $job): bool => $job->handle === 'octocat');

    signedApiRequest($this->keyPair, 'GET', route('api-profile-lookup.show', 'octocat'))->assertJsonPath('status', 'pending');
});

it('answers not found once a lookup finds the handle, so the app opens its page instead', function (): void {
    Cache::put('github-profiles:looking-up:octocat', true, 30);
    signedApiRequest($this->keyPair, 'GET', route('api-profile-lookup.show', 'Octocat'))->assertJsonPath('status', 'pending');

    Profile::factory()->create(['handle' => 'OctoCat']);
    Cache::forget('github-profiles:looking-up:octocat');

    signedApiRequest($this->keyPair, 'GET', route('api-profile-lookup.show', 'Octocat'))->assertNotFound();
});

it('looks up only handles GitHub could have that nobody here has, and not again those it has no account for', function (string $handle): void {
    Queue::fake([LookUpGitHubProfile::class]);
    Profile::factory()->create(['handle' => 'taken']);
    Cache::put('github-profiles:not-found:nobody', 'missing', 60);

    signedApiRequest($this->keyPair, 'POST', route('api-profile-lookup.store'), ['handle' => $handle])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('handle');

    Queue::assertNothingPushed();
})->with(['taken, whatever the case' => 'Taken', 'no GitHub account, whatever the case' => 'Nobody', 'an underscore' => 'oct_cat', 'empty' => '']);

it('lets only signed requests look handles up', function (): void {
    Queue::fake([LookUpGitHubProfile::class]);

    $this->getJson(route('api-profile-lookup.show', 'octocat'))->assertUnauthorized();
    $this->postJson(route('api-profile-lookup.store'), ['handle' => 'octocat'])->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('shares the 30 lookups an hour with the web, then answers with a code', function (): void {
    Queue::fake([LookUpGitHubProfile::class]);

    foreach (range(1, 15) as $index) {
        $this->actingAs($this->member)->post(route('profile-lookup.store'), ['handle' => "octocat-{$index}"])->assertSessionHasNoErrors();
    }

    foreach (range(16, 30) as $index) {
        signedApiRequest($this->keyPair, 'POST', route('api-profile-lookup.store'), ['handle' => "octocat-{$index}"])->assertAccepted();
    }

    signedApiRequest($this->keyPair, 'POST', route('api-profile-lookup.store'), ['handle' => 'octocat-31'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'rate_limited')
        ->assertHeader('Retry-After');

    Queue::assertPushed(LookUpGitHubProfile::class, 30);
});
