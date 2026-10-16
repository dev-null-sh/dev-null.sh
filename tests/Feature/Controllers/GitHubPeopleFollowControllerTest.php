<?php

declare(strict_types=1);

use App\Jobs\DiscoverGitHubPeople;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function completedGitHubPeople(User $user, User ...$people): void
{
    Cache::put("github-people:{$user->id}", [
        'id' => (string) Str::uuid(),
        'status' => 'ready',
        'github_ids' => array_map(fn (User $person): string => $person->github_id, $people),
        'username' => $user->username,
        'page' => 1,
        'scanned' => count($people),
        'fetched_at' => now()->toIso8601String(),
        'retry_at' => null,
    ], 3600);
}

it('requires authentication to follow discovered people', function (): void {
    $person = User::factory()->create();

    $this->post(route('github-people-follow.store'), ['user_ids' => [$person->id]])
        ->assertRedirectToRoute('home');

    expect($person->followers()->count())->toBe(0);
});

it('follows only the selected connections on behalf of the current user', function (bool $truncated): void {
    $user = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $unselected = User::factory()->create();
    $other = User::factory()->create();
    completedGitHubPeople($user, $first, $second, $unselected);
    $state = Cache::get("github-people:{$user->id}");
    Cache::put("github-people:{$user->id}", [...$state, 'truncated' => $truncated], 3600);

    $response = $this->actingAs($user)->post(route('github-people-follow.store'), [
        'user_ids' => [$first->id, $second->id],
        'follower_id' => $other->id,
    ]);

    $response->assertRedirectToRoute('github-people.index');

    expect($user->following()->pluck('users.id')->all())->toEqualCanonicalizing([$first->id, $second->id]);
    expect($other->following()->count())->toBe(0);
    expect($unselected->followers()->count())->toBe(0);
    expect($first->notifications()->sole()->actor_id)->toBe($user->id);
    expect($second->notifications()->sole()->actor_id)->toBe($user->id);
    Http::assertNothingSent();
})->with(['complete results' => false, 'capped results' => true]);

it('rejects an incomplete or expired discovery before following', function (string $status): void {
    $user = User::factory()->create();
    $person = User::factory()->create();
    completedGitHubPeople($user, $person);
    $state = Cache::get("github-people:{$user->id}");
    $state['status'] = $status;
    Cache::put("github-people:{$user->id}", $state, 3600);

    $response = $this->actingAs($user)->fromRoute('github-people.index')
        ->post(route('github-people-follow.store'), ['user_ids' => [$person->id]]);

    $response->assertRedirectToRoute('github-people.index')->assertSessionHasErrors('user_ids');

    expect($user->following()->count())->toBe(0);
    expect($person->notifications()->count())->toBe(0);
})->with(['idle', 'loading', 'unavailable', 'rate_limited', 'renamed']);

it('returns to discovery when the cached results expire', function (): void {
    $user = User::factory()->create();
    $person = User::factory()->create();
    completedGitHubPeople($user, $person);
    $this->travel(61)->minutes();

    $response = $this->actingAs($user)->fromRoute('github-people.index')
        ->post(route('github-people-follow.store'), ['user_ids' => [$person->id]]);

    $message = 'These discovery results are unavailable. Find your GitHub people again.';
    $response->assertRedirectToRoute('github-people.index')->assertSessionHasErrors(['user_ids' => $message]);

    expect($user->following()->count())->toBe(0);
    expect($person->notifications()->count())->toBe(0);

    $this->withCookie(config('session.cookie'), session()->getId())
        ->get(route('github-people.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('github-people/index')
        ->where('discovery.status', 'idle')
        ->where('errors.user_ids', $message));
});

it('rejects people outside the current account matches including self', function (string $selection): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $person = User::factory()->create();
    completedGitHubPeople($user, $user);
    completedGitHubPeople($other, $person);
    $id = $selection === 'self' ? $user->id : $person->id;

    $response = $this->actingAs($user)->fromRoute('github-people.index')
        ->post(route('github-people-follow.store'), ['user_ids' => [$id]]);

    $response->assertRedirectToRoute('github-people.index')->assertSessionHasErrors('user_ids.0');
    expect($user->following()->count())->toBe(0);
})->with(['self', 'another account discovery']);

it('validates the selected connection list', function (array $input, string $field): void {
    $user = User::factory()->create();
    completedGitHubPeople($user);

    $response = $this->actingAs($user)->fromRoute('github-people.index')
        ->post(route('github-people-follow.store'), $input);

    $response->assertRedirectToRoute('github-people.index')->assertSessionHasErrors($field);
    expect($user->following()->count())->toBe(0);
})->with([
    'missing selection' => [[], 'user_ids'],
    'empty selection' => [['user_ids' => []], 'user_ids'],
    'not an array' => [['user_ids' => 'someone'], 'user_ids'],
    'not a list' => [['user_ids' => ['person' => 'someone']], 'user_ids'],
    'invalid identifier' => [['user_ids' => ['not-a-uuid']], 'user_ids.0'],
    'too many selections' => [['user_ids' => array_fill(0, 101, 'not-a-uuid')], 'user_ids'],
]);

it('rejects the whole selection when an account is blocked after discovery', function (bool $outgoing): void {
    $user = User::factory()->create();
    $person = User::factory()->create();
    $visible = User::factory()->create();
    completedGitHubPeople($user, $visible, $person);
    [$blocker, $blocked] = $outgoing ? [$user, $person] : [$person, $user];
    $blocker->blocking()->attach($blocked);

    $response = $this->actingAs($user)->fromRoute('github-people.index')
        ->post(route('github-people-follow.store'), ['user_ids' => [$visible->id, $person->id]]);

    $response->assertRedirectToRoute('github-people.index')->assertSessionHasErrors('user_ids.1');
    expect($user->following()->count())->toBe(0);
    expect($person->notifications()->count())->toBe(0);
    expect($visible->notifications()->count())->toBe(0);
    Http::assertNothingSent();
})->with(['blocked by viewer' => true, 'blocked by discovered person' => false]);

it('rejects duplicate selected connections', function (): void {
    $user = User::factory()->create();
    $person = User::factory()->create();
    completedGitHubPeople($user, $person);

    $response = $this->actingAs($user)->fromRoute('github-people.index')
        ->post(route('github-people-follow.store'), ['user_ids' => [$person->id, $person->id]]);

    $response->assertSessionHasErrors('user_ids.0');

    expect($user->following()->count())->toBe(0);
});

it('handles a repeated follow submission without duplicate notifications', function (): void {
    $user = User::factory()->create();
    $person = User::factory()->create();
    completedGitHubPeople($user, $person);

    $this->actingAs($user)->post(route('github-people-follow.store'), ['user_ids' => [$person->id]])
        ->assertRedirectToRoute('github-people.index');
    $this->post(route('github-people-follow.store'), ['user_ids' => [$person->id]])
        ->assertRedirectToRoute('github-people.index');

    expect($user->following()->count())->toBe(1);
    expect($person->notifications()->count())->toBe(1);
});

it('keeps discovery and bulk follow rate limits independent', function (array $endpoints): void {
    $user = User::factory()->create();
    $person = User::factory()->create();
    completedGitHubPeople($user, $person);
    Queue::fake([DiscoverGitHubPeople::class]);

    foreach ($endpoints as [$route, $limit]) {
        foreach (range(1, $limit) as $attempt) {
            $this->actingAs($user)->post(route($route), ['user_ids' => [$person->id]])
                ->assertRedirectToRoute('github-people.index');
        }
    }

    foreach ($endpoints as [$route]) {
        $this->post(route($route), ['user_ids' => [$person->id]])->assertTooManyRequests();
    }

    expect($user->following()->count())->toBe(1);
    expect($person->notifications()->count())->toBe(1);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with([
    'discovery first' => [[['github-people.store', 6], ['github-people-follow.store', 10]]],
    'bulk follows first' => [[['github-people-follow.store', 10], ['github-people.store', 6]]],
]);
