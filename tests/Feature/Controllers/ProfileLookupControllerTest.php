<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\LookUpGitHubProfile;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

it('asks guests to sign in to look a handle up, on a missing page kept out of search engines', function (): void {
    Queue::fake();

    $this->get('/@octocat')
        ->assertNotFound()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertInertia(fn ($page) => $page
            ->component('profile/lookup')
            ->where('handle', 'octocat')
            ->where('lookup', null));

    Queue::assertNothingPushed();
    expect(session('url.intended'))->toBe(url('/@octocat'));
});

it('shows members where looking the handle up stands', function (Closure $setUp, string $status, bool $resumes): void {
    $setUp();

    $this->actingAs(User::factory()->create())
        ->get('/@Octocat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('profile/lookup')
            ->where('handle', 'Octocat')
            ->where('lookup.status', $status)
            ->where('lookup.resumesAt', fn (?string $resumesAt): bool => $resumes === ($resumesAt !== null)));
})->with([
    'never looked up' => [fn (): null => null, 'idle', false],
    'being looked up' => [fn () => Cache::put('github-profiles:looking-up:octocat', true, 30), 'pending', false],
    'no GitHub account' => [fn () => Cache::put('github-profiles:not-found:octocat', 'missing', 60), 'missing', false],
    'waiting for the rate limit' => [fn () => Cache::put('github-organizations:paused', now()->addMinutes(30)->getTimestamp(), 60), 'paused', true],
]);

it('answers a missing page for handles GitHub could not have, or of people who deleted their account', function (string $handle): void {
    Cache::put('github-profiles:not-found:gone', 'deleted', 60);

    $this->actingAs(User::factory()->create())->get("/@{$handle}")->assertNotFound();
    auth()->logout();
    $this->get("/@{$handle}")->assertNotFound();

    expect(session('url.intended'))->toBeNull();
})->with(['an underscore' => 'oct_cat', 'a trailing dash' => 'octocat-', 'too long' => str_repeat('a', 40), 'deleted' => 'gone']);

it('turns into the profile once the lookup finds it', function (): void {
    $member = User::factory()->create();
    Profile::factory()->create(['handle' => 'octocat']);

    $this->actingAs($member)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
            'X-Inertia-Partial-Component' => 'profile/lookup',
            'X-Inertia-Partial-Data' => 'lookup',
        ])
        ->get('/@octocat')
        ->assertOk()
        ->assertJsonPath('component', 'profile/unclaimed');
});

it('looks a handle up for a member, which the page then shows pending', function (): void {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->from('/@Octocat')
        ->post(route('profile-lookup.store'), ['handle' => 'Octocat'])
        ->assertRedirect('/@Octocat');

    Queue::assertPushed(LookUpGitHubProfile::class, fn (LookUpGitHubProfile $job): bool => $job->handle === 'octocat');

    expect(Cache::has('github-profiles:looking-up:octocat'))->toBeTrue();

    $this->travel(LookUpGitHubProfile::WAIT_SECONDS + 1)->seconds();

    expect(Cache::has('github-profiles:looking-up:octocat'))->toBeFalse();
});

it('looks up only handles GitHub could have that nobody here has, and not again those it has no account for', function (string $handle): void {
    Queue::fake();
    Profile::factory()->create(['handle' => 'taken']);
    Cache::put('github-profiles:not-found:nobody', 'missing', 60);

    $this->actingAs(User::factory()->create())
        ->post(route('profile-lookup.store'), ['handle' => $handle])
        ->assertSessionHasErrors('handle');

    Queue::assertNothingPushed();
})->with(['taken, whatever the case' => 'Taken', 'no GitHub account, whatever the case' => 'Nobody', 'an underscore' => 'oct_cat', 'empty' => '']);

it('lets only members look handles up', function (): void {
    Queue::fake();

    $this->post(route('profile-lookup.store'), ['handle' => 'octocat'])->assertRedirect(route('home'));

    Queue::assertNothingPushed();
});

it('lets a member look up 30 handles an hour, then tells them on the page', function (): void {
    Queue::fake();
    $member = User::factory()->create();

    foreach (range(1, 30) as $index) {
        $this->actingAs($member)->post(route('profile-lookup.store'), ['handle' => "octocat-{$index}"])->assertSessionHasNoErrors();
    }

    $this->actingAs($member)
        ->from('/@octocat-31')
        ->post(route('profile-lookup.store'), ['handle' => 'octocat-31'])
        ->assertRedirect('/@octocat-31')
        ->assertInertiaFlash('lookupThrottled', true);

    Queue::assertPushed(LookUpGitHubProfile::class, 30);
});
