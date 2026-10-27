<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists the people a user follows who follow them back', function (): void {
    $user = User::factory()->create();
    $mutual = User::factory()->create();
    $user->following()->attach([$mutual->id, User::factory()->create()->id]);
    $user->followers()->attach([$mutual->id, User::factory()->create()->id]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-mutuals.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/connections')
            ->where('type', 'mutuals')
            ->has('users.data', 1)
            ->where('users.data.0.id', $mutual->id)
            ->missing('users.data.0.email'));
});

it('lists the newest follow first', function (): void {
    $user = User::factory()->create();
    $earlier = User::factory()->create();
    $later = User::factory()->create();
    $user->following()->attach($earlier, ['created_at' => now()->subMinute()]);
    $user->following()->attach($later);
    $user->followers()->attach([$earlier->id, $later->id]);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user-mutuals.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 2)
            ->where('users.data.0.id', $later->id)
            ->where('users.data.1.id', $earlier->id));
});

it('marks the users the viewer follows', function (): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach($followed);
    $user->followers()->attach($followed);
    $viewer->following()->attach($followed);

    $response = $this->actingAs($viewer)
        ->get(route('user-mutuals.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.is_followed', true));
});

it('looks missing to people across a block', function (bool $viewerBlocks): void {
    $viewer = User::factory()->create();
    $user = User::factory()->create();

    $viewerBlocks ? $viewer->blocking()->attach($user) : $user->blocking()->attach($viewer);

    $response = $this->actingAs($viewer)
        ->get(route('user-mutuals.index', $user));

    $response->assertNotFound();
})->with([true, false]);

it('lists them to guests, without anyone marked as followed', function (): void {
    $user = User::factory()->create();
    $mutual = User::factory()->create();
    $user->following()->attach($mutual);
    $user->followers()->attach($mutual);

    $response = $this->get(route('user-mutuals.index', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/connections')
            ->has('users.data', 1)
            ->where('users.data.0.is_followed', false));
});

it('previews the list to guests without letting search engines index it', function (): void {
    $user = User::factory()->create(['handle' => 'octocat', 'name' => 'The Octocat']);

    $this->get(route('user-mutuals.index', $user))
        ->assertOk()
        ->assertSee('<title>Mutuals of The Octocat (@octocat) on /dev/null</title>', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('<link rel="canonical" href="'.route('user-mutuals.index', $user).'">', false);
});

it('does not count the user for guests loading more of the list', function (): void {
    $user = User::factory()->create();
    $mutual = User::factory()->create();
    $user->following()->attach($mutual);
    $user->followers()->attach($mutual);

    DB::enableQueryLog();

    $this->get(route('user-mutuals.index', $user), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'user/connections',
        'X-Inertia-Partial-Data' => 'users',
    ])
        ->assertOk()
        ->assertJsonCount(1, 'props.users.data')
        ->assertJsonMissingPath('props.user');

    expect(collect(DB::getQueryLog())->pluck('query')->all())
        ->not->toBeEmpty()
        ->each->not->toContain('count(*)');
});
