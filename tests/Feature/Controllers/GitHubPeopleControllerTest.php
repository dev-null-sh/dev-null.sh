<?php

declare(strict_types=1);

use App\Jobs\DiscoverGitHubPeople;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('requires authentication to view or start discovery', function (): void {
    Queue::fake();

    $this->get(route('github-people.index'))->assertRedirectToRoute('home');
    $this->post(route('github-people.store'))->assertRedirectToRoute('home');

    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('waits for an explicit request before checking GitHub', function (): void {
    $user = User::factory()->create();
    Queue::fake();

    $response = $this->actingAs($user)->get(route('github-people.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('github-people/index')
        ->where('discovery.status', 'idle')
        ->has('discovery.users', 0)
        ->where('discovery.scanned', 0));
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('starts discovery only for the signed in account', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);

    $response = $this->actingAs($user)->post(route('github-people.store'), [
        'user_id' => $other->id,
        'username' => $other->username,
    ]);

    $response->assertRedirectToRoute('github-people.index');

    expect(Cache::get("github-people:{$user->id}")['status'])->toBe('loading');
    expect(Cache::has("github-people:{$other->id}"))->toBeFalse();
    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
    Http::assertNothingSent();
});

it('discovers a GitHub connection and renders only its public devnull profile', function (): void {
    $user = User::factory()->create(['github_id' => '100', 'username' => 'old-name']);
    $person = User::factory()->create(['github_id' => '200', 'username' => 'devnull-name']);
    User::factory()->create(['github_id' => '300', 'username' => 'github-name']);
    $user->following()->attach($person);
    Http::fake([
        'api.github.com/user/100' => Http::response(['id' => 100, 'login' => 'new-name']),
        'api.github.com/users/new-name/following*' => Http::response([
            ['id' => 200, 'login' => 'github-name'],
            ['id' => 999, 'login' => 'not-on-devnull'],
        ]),
    ]);

    $this->actingAs($user)->post(route('github-people.store'))->assertRedirectToRoute('github-people.index');
    $response = $this->get(route('github-people.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('github-people/index')
        ->where('discovery.status', 'ready')
        ->where('discovery.scanned', 2)
        ->has('discovery.users', 1)
        ->where('discovery.users.0.id', $person->id)
        ->where('discovery.users.0.username', 'devnull-name')
        ->where('discovery.users.0.is_followed', true)
        ->missing('discovery.github_ids')
        ->missing('discovery.id')
        ->missing('discovery.users.0.github_id')
        ->missing('discovery.users.0.email')
        ->missing('discovery.users.0.remember_token'));
    Http::assertSentCount(2);
});

it('does not show another account discovery snapshot', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Cache::put("github-people:{$other->id}", ['status' => 'ready', 'github_ids' => ['123']], 3600);

    $response = $this->actingAs($user)->get(route('github-people.index', ['user_id' => $other->id]));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('discovery.status', 'idle')
        ->has('discovery.users', 0));
    Http::assertNothingSent();
});

it('rate limits repeated discovery requests without queuing duplicate scans', function (): void {
    $user = User::factory()->create();
    Queue::fake([DiscoverGitHubPeople::class]);

    foreach (range(1, 6) as $attempt) {
        $this->actingAs($user)->post(route('github-people.store'))->assertRedirectToRoute('github-people.index');
    }

    $this->post(route('github-people.store'))->assertTooManyRequests();

    Queue::assertPushed(DiscoverGitHubPeople::class, 1);
});
