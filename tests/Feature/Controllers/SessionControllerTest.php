<?php

declare(strict_types=1);

use App\Enums\Log;
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

    $response->assertRedirectToRoute('log.show', Log::Following);

    $user = User::query()->where('github_id', '583231')->sole();

    expect($user)
        ->username->toBe('octocat')
        ->name->toBe('The Octocat')
        ->email->toBe('octocat@github.com')
        ->avatar_url->toBe('https://avatars.githubusercontent.com/u/583231')
        ->bio->toBe('I am the Octocat.')
        ->location->toBe('San Francisco')
        ->website->toBe('https://github.blog')
        ->company->toBe('@github');

    $this->assertAuthenticatedAs($user);
});

it('updates an existing user on login', function (): void {
    $user = User::factory()->create([
        'github_id' => '583231',
        'username' => 'old-username',
    ]);

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
    ]));

    $response = $this->get(route('login.store'));

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect(User::query()->count())->toBeOne()
        ->and($user->refresh()->username)->toBe('octocat');

    $this->assertAuthenticatedAs($user);
});

it('queues a sync of the ssh keys of the user on login', function (): void {
    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => 'octocat',
    ]));

    $this->get(route('login.store'));

    $user = User::query()->where('github_id', '583231')->sole();

    Queue::assertPushed(SyncGitHubSshKeysJob::class, fn (SyncGitHubSshKeysJob $job): bool => $job->user->is($user));
});

it('redirects to the intended url after login', function (): void {
    $user = User::factory()->create();

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
            'message' => __('Unable to sign in with GitHub. Please try again.'),
        ]);

    $this->assertGuest();
});

it('fails when the state is invalid', function (): void {
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $response = $this->get(route('login.store'));

    $response->assertRedirectToRoute('home')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('Unable to sign in with GitHub. Please try again.'),
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
