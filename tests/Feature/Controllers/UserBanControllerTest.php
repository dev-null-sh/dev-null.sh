<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Enums\ModerationReason;
use App\Models\Ban;
use App\Models\Commit;
use App\Models\ScheduledCommit;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GitHubUser;

beforeEach(function (): void {
    Queue::fake();
});

it('may ban a user', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $user = User::factory()->create(['handle' => 'octocat', 'github_id' => '583231']);

    config(['app.admins' => ['admin']]);

    $this->actingAs($admin)
        ->fromRoute('user.show', $user)
        ->post(route('user-ban.store', $user), ['reason' => 'ai_generated'])
        ->assertRedirectToRoute('user.show', $user)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Banned @octocat. All they can do now is delete their account.']);

    expect(Ban::query()->sole())
        ->github_id->toBe('583231')
        ->reason->toBe(ModerationReason::AiGenerated);

    expect($user->refresh()->isBanned())->toBeTrue();

    $this->post(route('user-ban.store', $user), ['reason' => 'spam']);

    expect(Ban::query()->sole()->reason)->toBe(ModerationReason::Spam);
});

it('validates the reason of a ban', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $user = User::factory()->create();

    config(['app.admins' => ['admin']]);

    $this->actingAs($admin)
        ->post(route('user-ban.store', $user), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($user->isBanned())->toBeFalse();
});

it('may not be given by someone who is not an admin, nor to an admin', function (string $actor, string $target): void {
    User::factory()->create(['handle' => 'admin']);
    User::factory()->create(['handle' => 'other-admin']);
    User::factory()->create(['handle' => 'someone']);
    User::factory()->create(['handle' => 'octocat']);

    config(['app.admins' => ['admin', 'other-admin']]);

    $this->actingAs(User::query()->whereRelation('profile', 'handle', $actor)->sole())
        ->post(route('user-ban.store', ['user' => $target]), ['reason' => 'spam'])
        ->assertForbidden();

    $this->delete(route('user-ban.destroy', ['user' => $target]))->assertForbidden();

    expect(Ban::query()->count())->toBe(0);
})->with([
    'by a user' => ['someone', 'octocat'],
    'to another admin' => ['admin', 'other-admin'],
    'to themselves' => ['admin', 'admin'],
]);

it('may unban a user', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $user = User::factory()->banned()->create(['handle' => 'octocat']);

    config(['app.admins' => ['admin']]);

    $this->actingAs($admin)
        ->fromRoute('user.show', $user)
        ->delete(route('user-ban.destroy', $user))
        ->assertRedirectToRoute('user.show', $user)
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unbanned @octocat.']);

    expect($user->refresh()->isBanned())->toBeFalse()
        ->and(Ban::query()->count())->toBe(0);
});

it('sends a banned user from every page to the one that tells them why', function (string $method, string $route, array $parameters): void {
    $user = User::factory()->banned()->create(['handle' => 'octocat']);
    $parameters = array_map(fn (mixed $parameter): mixed => $parameter === 'commit' ? Commit::factory()->create() : $parameter, $parameters);

    $this->actingAs($user)
        ->call($method, route($route, $parameters), ['body' => 'Still here.'])
        ->assertRedirectToRoute('banned.show');

    expect($user->commits()->count())->toBe(0);
})->with([
    'the log' => ['GET', 'log.show', ['log' => 'following']],
    'their profile' => ['GET', 'user.show', ['user' => 'octocat']],
    'the settings' => ['GET', 'user-profile.edit', []],
    'committing' => ['POST', 'commit.store', []],
    'starring' => ['POST', 'commit-star.store', ['commit' => 'commit']],
    'the home page' => ['GET', 'home', []],
]);

it('answers the requests of a banned user that expect JSON with a refusal', function (): void {
    $user = User::factory()->banned()->create();

    $this->actingAs($user)
        ->getJson(route('commit-star-count.index'))
        ->assertForbidden()
        ->assertExactJson(['message' => 'Your account is banned from /dev/null.', 'banned' => true]);
});

it('shows a banned user why they were banned', function (): void {
    $user = User::factory()->banned(ModerationReason::EngagementBait)->create();

    $this->actingAs($user)
        ->get(route('banned.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('banned/show')
            ->where('reason', 'Engagement bait')
            ->where('bannedAt', now()->startOfSecond()->toISOString()));
});

it('sends everyone else from the banned page to their log', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('banned.show'))
        ->assertRedirectToRoute('log.show', $user->log);
});

it('lets a banned user read the terms and privacy policy the banned page points to', function (string $route, string $component): void {
    $user = User::factory()->banned()->create();

    $this->actingAs($user)
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    'the terms' => ['terms.show', 'terms/show'],
    'the privacy policy' => ['privacy.show', 'privacy/show'],
]);

it('lets a banned user sign out', function (): void {
    $user = User::factory()->banned()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirectToRoute('home');

    $this->assertGuest();
});

it('lets a banned user delete their account, and keeps the ban for when they sign in again', function (): void {
    $user = User::factory()->banned()->create(['github_id' => '583231']);

    $this->actingAs($user)
        ->delete(route('user.destroy'))
        ->assertRedirectToRoute('home');

    expect(User::query()->count())->toBe(0)
        ->and(Ban::query()->count())->toBe(1);

    Socialite::fake('github', GitHubUser::fake(['id' => '583231', 'nickname' => 'octocat']));

    $this->get(route('login.store'))
        ->assertRedirectToRoute('home')
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'Sign up blocked. This GitHub account is banned from /dev/null.',
        ]);

    $this->assertGuest();
    expect(User::query()->count())->toBe(0);
});

it('signs in a banned user who still has an account, straight to the banned page', function (): void {
    $user = User::factory()->banned()->create(['github_id' => '583231']);

    Socialite::fake('github', GitHubUser::fake(['id' => '583231', 'nickname' => $user->handle]));
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('hasSponsorsListing')->andReturnFalse();

    $this->get(route('login.store'))->assertRedirect();

    $this->assertAuthenticatedAs($user);
    $this->get(route('log.show', 'following'))->assertRedirectToRoute('banned.show');
});

it('refuses every signed request of a banned user but deleting their account', function (): void {
    $user = User::factory()->banned()->create();
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'GET', route('api-current-user.show'))
        ->assertForbidden()
        ->assertExactJson(['code' => 'banned', 'message' => 'Your account is banned from /dev/null. All you can do is delete it.']);

    signedApiRequest($keyPair, 'DELETE', route('api-current-user.destroy'))->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});

it('cancels the scheduled commits of the user it bans, so lifting the ban does not send them all at once', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $user = User::factory()->create();
    $pending = ScheduledCommit::factory()->for($user)->create(['scheduled_at' => now()->addHour()]);
    $submitted = ScheduledCommit::factory()->for($user)->create(['scheduled_at' => now()->subHour(), 'submitted_at' => now()->subHour()]);

    config(['app.admins' => ['admin']]);

    $this->actingAs($admin)->post(route('user-ban.store', $user), ['reason' => 'spam']);

    expect($pending->refresh()->canceled_at)->not->toBeNull()
        ->and($submitted->refresh()->canceled_at)->toBeNull();

    $this->delete(route('user-ban.destroy', $user));
    $this->travel(2)->hours();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect($pending->refresh()->submitted_at)->toBeNull();
});

it('never submits the scheduled commits of a banned user', function (): void {
    $user = User::factory()->create();
    $scheduled = ScheduledCommit::factory()->for($user)->create(['scheduled_at' => now()->subMinute()]);
    Ban::factory()->create(['github_id' => $user->github_id]);

    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    expect($scheduled->refresh()->submitted_at)->toBeNull();
});
