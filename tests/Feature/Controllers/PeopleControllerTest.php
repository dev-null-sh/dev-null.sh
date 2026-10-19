<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Pagination\Cursor;

beforeEach(function (): void {
    $this->withoutVite();
});

it('requires sign in to discover people', function (): void {
    $this->get(route('people.index'))->assertRedirect(route('home'));
});

it('lists eligible people with their suggestion reasons', function (): void {
    $user = User::factory()->has(Commit::factory())->create();
    $friend = User::factory()->has(Commit::factory())->create();
    $candidate = User::factory()->has(Commit::factory())->create();
    $user->following()->attach($friend);
    $candidate->followers()->attach($friend);

    $this->actingAs($user)->get(route('people.index'))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('people/index')
            ->has('users.data', 1)
            ->where('users.data.0.id', $candidate->id)
            ->where('users.data.0.followed_by_following_count', 1)
            ->where('users.data.0.followers_count', 1)
            ->missing('users.data.0.email')
            ->missing('users.data.0.github_id')
            ->where('users.next_cursor', null));
});

it('only lists people with visible top level commits outside mutes and blocks', function (): void {
    $user = User::factory()->create();
    $candidate = User::factory()->has(Commit::factory())->create();
    $muted = User::factory()->has(Commit::factory())->create();
    $blocked = User::factory()->has(Commit::factory())->create();
    $blocker = User::factory()->has(Commit::factory())->create();
    $user->muting()->attach($muted);
    $user->blocking()->attach($blocked);
    $blocker->blocking()->attach($user);

    User::factory()->create();
    User::factory()->has(Commit::factory()->pending())->create();
    User::factory()->has(Commit::factory()->rejected())->create();
    User::factory()->has(Commit::factory()->trashed())->create();
    $parent = Commit::factory()->for($user)->create();
    User::factory()->has(Commit::factory()->for($parent, 'parent'))->create();

    $this->actingAs($user)->get(route('people.index'))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $candidate->id));
});

it('keeps browsing in member order after following people from the first page', function (): void {
    $user = User::factory()->create();
    $candidates = User::factory()->count(35)->has(Commit::factory())->create()->sortByDesc('id')->values();
    $candidates->first()->following()->attach($candidates->last());

    $response = $this->actingAs($user)->get(route('people.index'))->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('users.data', 30)
        ->where('users.data.0.id', $candidates->first()->id)
        ->where('users.data.29.id', $candidates[29]->id));

    $user->following()->attach($candidates->take(30)->modelKeys());

    $this->get(route('people.index', ['cursor' => $response->inertiaProps('users.next_cursor')]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 5)
            ->where('users.data', fn ($users): bool => collect($users)->pluck('id')->all() === $candidates->slice(30)->values()->modelKeys())
            ->where('users.next_cursor', null));
});

it('excludes people followed or blocked before loading the next page', function (): void {
    $user = User::factory()->create();
    $candidates = User::factory()->count(33)->has(Commit::factory())->create()->sortByDesc('id')->values();

    $response = $this->actingAs($user)->get(route('people.index'))->assertOk();
    $user->following()->attach($candidates[30]);
    $candidates[31]->blocking()->attach($user);

    $this->get(route('people.index', ['cursor' => $response->inertiaProps('users.next_cursor')]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $candidates[32]->id)
            ->where('users.next_cursor', null));
});

it('rejects invalid discovery cursors', function (mixed $cursor): void {
    $this->actingAs(User::factory()->create())
        ->getJson(route('people.index', ['cursor' => $cursor]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cursor');
})->with([
    'array' => [['unexpected']],
    'too long' => [str_repeat('a', 2049)],
    'malformed' => ['invalid'],
    'missing id' => [new Cursor([])->encode()],
    'invalid id' => [new Cursor(['id' => 'invalid'])->encode()],
    'invalid direction' => [mb_rtrim(strtr(base64_encode('{"id":"019a777f-a466-7a88-9d57-0657dd7094cc","_pointsToNextItems":"true"}'), '+/', '-_'), '=')],
]);
