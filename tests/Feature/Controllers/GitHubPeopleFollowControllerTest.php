<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

it('follows only the selected connections on behalf of the current user', function (): void {
    $user = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $unselected = User::factory()->create();
    $other = User::factory()->create();
    completedGitHubPeople($user, $first, $second, $unselected);

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
});

it('rejects an incomplete or expired discovery before following', function (string $status): void {
    $user = User::factory()->create();
    $person = User::factory()->create();
    completedGitHubPeople($user, $person);
    $state = Cache::get("github-people:{$user->id}");
    $state['status'] = $status;
    Cache::put("github-people:{$user->id}", $state, 3600);

    $response = $this->actingAs($user)->post(route('github-people-follow.store'), ['user_ids' => [$person->id]]);

    $response->assertForbidden();

    expect($user->following()->count())->toBe(0);
    expect($person->notifications()->count())->toBe(0);
})->with(['idle', 'loading', 'unavailable', 'rate_limited']);

it('rejects following when the cached results have expired', function (): void {
    $user = User::factory()->create();
    $person = User::factory()->create();
    completedGitHubPeople($user, $person);
    $this->travel(61)->minutes();

    $response = $this->actingAs($user)->post(route('github-people-follow.store'), ['user_ids' => [$person->id]]);

    $response->assertForbidden();

    expect($user->following()->count())->toBe(0);
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
