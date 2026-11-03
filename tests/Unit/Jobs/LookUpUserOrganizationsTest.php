<?php

declare(strict_types=1);

use App\Actions\UpdateMentionedOrganizations;
use App\Contracts\Services\GitHubProvider;
use App\Jobs\LookUpUserOrganizations;
use App\Models\Organization;
use App\Models\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

it('looks the organizations the bio and company mention up, but not the users', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('laravel')->once()->andReturn(['login' => 'laravel', 'id' => 958072, 'type' => 'Organization', 'name' => 'Laravel', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]);
    $github->shouldReceive('accountByLogin')->with('nobody')->once()->andReturnNull();

    User::factory()->create(['handle' => 'taylorotwell']);
    $user = User::factory()->create(['bio' => 'Thanks @TaylorOtwell and @nobody', 'company' => '@laravel']);

    dispatch_sync(new LookUpUserOrganizations($user));

    expect(Organization::query()->get()->pluck('handle')->all())->toBe(['laravel']);
});

it('skips the organizations that keep GitHub from showing them, and looks the others up', function (Psr7Response $response): void {
    $forbidden = new RequestException(new Response($response));
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturnNull();
    $github->shouldReceive('accountByLogin')->with('restricted')->once()->andThrow($forbidden);
    $github->shouldReceive('accountByLogin')->with('laravel')->once()->andReturn(['login' => 'laravel', 'id' => 958072, 'type' => 'Organization', 'name' => 'Laravel', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]);
    $github->shouldReceive('account')->with('1')->once()->andThrow($forbidden);
    $github->shouldReceive('account')->with('6764390')->once()->andReturn(['login' => 'symfony', 'id' => 6764390, 'type' => 'Organization', 'name' => 'Symfony, SAS', 'avatar_url' => null, 'bio' => null, 'location' => null, 'blog' => '', 'company' => null]);

    $private = Organization::factory()->create(['github_id' => '1', 'handle' => 'private', 'name' => 'Private', 'github_synced_at' => now()->subDays(2)]);
    $symfony = Organization::factory()->create(['github_id' => '6764390', 'handle' => 'symfony', 'name' => 'Symfony', 'github_synced_at' => now()->subDays(2)]);
    $user = User::factory()->create(['bio' => 'Thanks @restricted and @laravel', 'company' => '@private @symfony']);

    dispatch_sync(new LookUpUserOrganizations($user));

    expect(Organization::query()->get()->pluck('handle')->sort()->values()->all())->toBe(['laravel', 'private', 'symfony'])
        ->and($private->refresh()->name)->toBe('Private')
        ->and($symfony->refresh()->name)->toBe('Symfony, SAS')
        ->and(Cache::has('github-organizations:not-found:restricted'))->toBeFalse();
})->with([
    'an IP allow list' => fn (): Psr7Response => new Psr7Response(403, [], '{"message":"Although you appear to have the correct authorization credentials, the `acme` organization has an IP allow list enabled, and your IP address is not permitted to access this resource."}'),
    'SAML single sign-on' => fn (): Psr7Response => new Psr7Response(403, ['X-GitHub-SSO' => 'required; url=https://github.com/orgs/acme/sso'], '{"message":"Resource protected by organization SAML enforcement."}'),
    'OAuth app access restrictions' => fn (): Psr7Response => new Psr7Response(403, [], '{"message":"Although you appear to have the correct authorization credentials, the `acme` organization has enabled OAuth App access restrictions, meaning that data access to third-parties is limited."}'),
]);

it('runs again once the rate limit resets when it cut the lookups short', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldNotReceive('accountByLogin');

    $resetsAt = now()->addMinutes(30)->startOfSecond();
    Cache::put('github-organizations:paused', $resetsAt->getTimestamp(), $resetsAt);
    $job = new LookUpUserOrganizations(User::factory()->create(['bio' => 'Building @laravel']))->withFakeQueueInteractions();

    $job->handle(resolve(UpdateMentionedOrganizations::class));

    $job->assertReleased($resetsAt);
});

it('is done once it looked every handle up', function (): void {
    $job = new LookUpUserOrganizations(User::factory()->create(['bio' => 'Building @laravel']))->withFakeQueueInteractions();

    $job->handle(resolve(UpdateMentionedOrganizations::class));

    $job->assertNotReleased();
});

it('is queued only for a bio or company that mentions someone', function (?string $bio, ?string $company, bool $queued): void {
    Queue::fake();

    $user = User::factory()->create(['bio' => $bio, 'company' => $company]);

    expect(LookUpUserOrganizations::dispatchFor($user))->toBe($queued);
    $queued
        ? Queue::assertPushed(LookUpUserOrganizations::class, fn (LookUpUserOrganizations $job): bool => $job->user->is($user))
        : Queue::assertNothingPushed();
})->with([
    'a bio mention' => ['Building @laravel', null, true],
    'a company mention' => [null, '@laravel', true],
    'no mention' => ['Building things', 'Acme', false],
    'nothing' => [null, null, false],
]);
