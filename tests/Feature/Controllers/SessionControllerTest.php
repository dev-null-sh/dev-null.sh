<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Enums\Log;
use App\Jobs\ImportGitHubFollowsJob;
use App\Jobs\LookUpUserOrganizations;
use App\Jobs\SyncGitHubSshKeysJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GitHubUser;

beforeEach(function (): void {
    Queue::fake();
});

it('redirects to github', function (): void {
    Socialite::fake('github');

    $response = $this->get(route('login'));

    $response->assertRedirect();
});

it('creates a user and logs them in', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnTrue();

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
        'name' => 'The Octocat',
        'email' => 'octocat@github.com',
        'avatar' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => 'I am the Octocat.',
        'location' => 'San Francisco',
        'blog' => 'https://github.blog',
        'company' => '@github',
    ]));

    $response = $this->get(route('login.store'));

    $response->assertRedirectToRoute('onboarding.show')
        ->assertInertiaFlash('signedUp', true);

    $user = User::query()->where('github_id', '583231')->sole();

    expect($user)
        ->handle->toBe('octocat')
        ->name->toBe('The Octocat')
        ->email->toBe('octocat@github.com')
        ->avatar_url->toBe('https://avatars.githubusercontent.com/u/583231')
        ->bio->toBe('I am the Octocat.')
        ->location->toBe('San Francisco')
        ->website->toBe('https://github.blog')
        ->company->toBe('@github')
        ->has_sponsors_listing->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('refuses a new signup from an account that fails the screening', function (): void {
    config([
        'signup.min_account_age_days' => 7,
        'signup.require_verified_email' => false,
    ]);

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
        'created_at' => now()->subDay()->toIso8601String(),
    ]));

    $response = $this->get(route('login.store'));

    $response->assertRedirectToRoute('home')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('Sign up blocked. GitHub accounts younger than :days days cannot join yet.', ['days' => 7]),
        ]);

    expect(User::query()->where('github_id', '583231')->exists())->toBeFalse();

    $this->assertGuest();
});

it('does not screen an account that has signed in before', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    config([
        'signup.min_account_age_days' => 7,
        'signup.require_verified_email' => true,
    ]);

    $user = User::factory()->create(['github_id' => '583231']);

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
        'email' => null,
        'created_at' => now()->toIso8601String(),
    ]));

    $this->get(route('login.store'))->assertRedirectToRoute('log.show', Log::Following);

    $this->assertAuthenticatedAs($user);
});

it('updates an existing user on login', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $user = User::factory()->create([
        'github_id' => '583231',
        'handle' => 'old-handle',
    ]);

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
    ]));

    $response = $this->get(route('login.store'));

    $response->assertRedirectToRoute('log.show', Log::Following)
        ->assertInertiaFlashMissing('signedUp');

    expect(User::query()->count())->toBeOne()
        ->and($user->refresh()->handle)->toBe('octocat');

    $this->assertAuthenticatedAs($user);
});

it('redirects to the log the user visited last on login', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    User::factory()->create(['github_id' => '583231', 'log' => Log::Trending]);

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
    ]));

    $response = $this->get(route('login.store'));

    $response->assertRedirectToRoute('log.show', Log::Trending);
});

it('sends a returning user who never finished the onboarding back to it', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $user = User::factory()->notOnboarded()->create(['github_id' => '583231']);

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
    ]));

    $response = $this->get(route('login.store'));

    $response->assertRedirectToRoute('onboarding.show');

    $this->assertAuthenticatedAs($user);
});

it('queues a sync of the ssh keys of the user on login', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
    ]));

    $this->get(route('login.store'));

    $user = User::query()->where('github_id', '583231')->sole();

    Queue::assertPushed(SyncGitHubSshKeysJob::class, fn (SyncGitHubSshKeysJob $job): bool => $job->user->is($user));
});

it('queues a lookup of the organizations the bio of the user mentions on login', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
        'company' => '@github',
    ]));

    $this->get(route('login.store'));

    $user = User::query()->where('github_id', '583231')->sole();

    Queue::assertPushed(LookUpUserOrganizations::class, fn (LookUpUserOrganizations $job): bool => $job->user->is($user));
});

it('queues an import of the github follows of the user on login, notifying only for new users', function (bool $exists, bool $notify): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    if ($exists) {
        User::factory()->create(['github_id' => '583231']);
    }

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
    ]));

    $this->get(route('login.store'));

    $user = User::query()->where('github_id', '583231')->sole();

    Queue::assertPushed(ImportGitHubFollowsJob::class, fn (ImportGitHubFollowsJob $job): bool => $job->user->is($user) && $job->notify === $notify);
})->with([
    'new user' => [false, true],
    'existing user' => [true, false],
]);

it('redirects to the intended url after login', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $user = User::factory()->create();
    User::factory()->create(['github_id' => '583231']);

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
    ]));

    $this->get(route('user.show', $user))
        ->assertOk();

    $response = $this->get(route('login.store'));

    $response->assertRedirectToRoute('user.show', $user);
});

it('fails when the user denies access', function (): void {
    $response = $this->get(route('login.store', ['error' => 'access_denied']));

    $response->assertRedirectToRoute('home')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Unable to sign in with GitHub. Please try again.',
        ]);

    $this->assertGuest();
});

it('starts the sign-in over once when it started in another browser', function (): void {
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $this->get(route('login.store'))->assertRedirectToRoute('login');

    $this->assertGuest();
});

it('fails when the state is still invalid after starting over', function (): void {
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $this->get(route('login.store'))->assertRedirectToRoute('login');
    $response = $this->get(route('login.store'));

    $response->assertRedirectToRoute('home')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Unable to sign in with GitHub. Please try again.',
        ]);

    $this->assertGuest();
});

it('redirects authenticated users away from login', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('login'));

    $response->assertRedirectToRoute('log.show', Log::Following);
});

it('may destroy a session', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('log.show', Log::Following)
        ->post(route('logout'));

    $response->assertRedirectToRoute('home');

    $this->assertGuest();
});

it('makes tom the first friend of a new user, but not of a returning one', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    Socialite::fake('github', GitHubUser::fake(['id' => '583231', 'nickname' => 'octocat', 'name' => 'The Octocat']));

    $this->get(route('login.store'));

    $user = User::query()->where('github_id', '583231')->sole();
    $tom = User::query()->whereRelation('profile', 'handle', 'tom')->sole();

    expect($user->isFollowing($tom))->toBeTrue()
        ->and($tom->isFollowing($user))->toBeTrue();

    $returning = User::factory()->create(['github_id' => '1']);

    Socialite::fake('github', GitHubUser::fake(['id' => '1', 'nickname' => $returning->handle, 'name' => $returning->name]));

    $this->post(route('logout'));
    $this->get(route('login.store'));

    expect($returning->isFollowing($tom))->toBeFalse();
});
