<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists the people the user mutes and blocks, most recent first', function (): void {
    $user = User::factory()->create();
    $olderMute = User::factory()->create(['username' => 'older_mute']);
    $newerMute = User::factory()->create(['username' => 'newer_mute']);
    $blocked = User::factory()->create(['username' => 'blocked']);
    $blocker = User::factory()->create();

    $user->muting()->attach($olderMute, ['created_at' => now()->subDay()]);
    $user->muting()->attach($newerMute);
    $user->blocking()->attach($blocked);
    $blocker->blocking()->attach($user);
    $blocker->muting()->attach($user);

    $response = $this->actingAs($user)->get(route('safety.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('safety/edit')
            ->has('muted', 2)
            ->where('muted.0.username', 'newer_mute')
            ->where('muted.1.username', 'older_mute')
            ->missing('muted.0.email')
            ->has('blocked', 1)
            ->where('blocked.0.username', 'blocked')
            ->where('blocked.0.lane', $blocked->lane()));
});

it('loads each list in a single query', function (): void {
    $user = User::factory()->create();

    $user->muting()->attach(User::factory()->count(3)->create());
    $user->blocking()->attach(User::factory()->count(3)->create());

    $response = $this->actingAs($user)->get(route('safety.edit'));

    $response->assertOk()
        ->assertInertia(function ($page): void {
            DB::enableQueryLog();

            $page->reloadOnly(['muted', 'blocked'], fn ($reload) => $reload->has('muted', 3)->has('blocked', 3));
        });

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $query): bool => str_contains($query, '"mutes"'))->all())->toHaveCount(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, '"blocks"'))->all())->toHaveCount(1);
});

it('requires authentication to see who they mute and block', function (): void {
    $this->get(route('safety.edit'))->assertRedirectToRoute('home');
});
