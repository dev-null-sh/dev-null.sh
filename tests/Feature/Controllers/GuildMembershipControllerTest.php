<?php

declare(strict_types=1);

use App\Models\Guild;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('lists the moderator first then joined members newest first in pages of fifty without private preferences', function (): void {
    $viewer = User::factory()->create(['handle' => 'admin']);
    $moderator = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $moderator->id]);
    $older = User::factory()->create();
    $members = User::factory()->count(50)->create();
    $guild->members()->attach($moderator, ['joined_at' => now()->subDays(2)]);
    $guild->members()->attach($older, ['joined_at' => now()->subDay()]);
    $guild->members()->attach($members->modelKeys(), ['joined_at' => now()]);
    $guild->muters()->attach(User::factory()->create(), ['muted_at' => now()]);
    Guild::factory()->create()->members()->attach(User::factory()->create(), ['joined_at' => now()]);

    $this->actingAs($viewer)->get(route('guild-membership.index', $guild->handle))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('guild/members')
        ->where('guild.handle', $guild->handle)
        ->has('users.data', 50)
        ->where('users.data.0.id', $moderator->id)
        ->where('users.data.0.is_moderator', true)
        ->where('users.data.1.is_moderator', false)
        ->where('users.data', fn ($users): bool => collect($users)->pluck('id')->all() === [$moderator->id, ...$members->sortByDesc('id')->take(49)->values()->modelKeys()])
        ->where('users.current_page', 1)
        ->where('users.per_page', 50)
        ->where('users.next_page_url', route('guild-membership.index', ['guild' => $guild->handle, 'page' => 2]))
        ->missing('users.data.0.email')
        ->missing('users.data.0.pivot'));

    $this->get(route('guild-membership.index', ['guild' => $guild->handle, 'page' => 2]))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->has('users.data', 2)
        ->where('users.data.0.id', $members->sortBy('id')->first()->id)
        ->where('users.data.1.id', $older->id)
        ->where('users.next_page_url', null));
});

it('keeps paused guild members accessible and supports an empty list', function (): void {
    $viewer = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => null]);

    $this->actingAs($viewer)->get(route('guild-membership.index', $guild->handle))
        ->assertOk()->assertInertia(fn ($page) => $page->has('users.data', 0)->where('users.next_page_url', null));

    $guild->members()->attach($viewer, ['joined_at' => now()]);

    $this->get(route('guild-membership.index', $guild->handle))
        ->assertOk()->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.id', $viewer->id)->where('users.data.0.is_moderator', false));
});

it('requires sign in and the guild preview feature to list members', function (): void {
    $guild = Guild::factory()->create();

    $this->get(route('guild-membership.index', $guild->handle))->assertRedirectToRoute('home');
    $this->actingAs(User::factory()->create())->get(route('guild-membership.index', $guild->handle))->assertNotFound();
});

it('rejects invalid member pages', function (mixed $page): void {
    $guild = Guild::factory()->create();

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->getJson(route('guild-membership.index', ['guild' => $guild->handle, 'page' => $page]))
        ->assertUnprocessable()->assertJsonValidationErrors('page');
})->with([0, 'not-a-page']);
