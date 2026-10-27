<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('shows admins where the metrics are heading', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));

    $response = $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('forecast.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/forecasts')
            ->has('forecasts', 4)
            ->has('forecasts.0.forecast', 14)
            ->where('milestones', [
                ['metric' => 'users', 'current' => 1, 'target' => 10, 'eta' => null],
                ['metric' => 'commits', 'current' => 0, 'target' => 10, 'eta' => null],
            ])
            ->where('month.0', ['metric' => 'signups', 'month' => '2026-10', 'to_date' => 1, 'projected' => 1, 'lower' => 1, 'upper' => 12])
            ->where('hasTraffic', false));
});

it('is a 404 for non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('forecast.show'));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $response = $this->get(route('forecast.show'));

    $response->assertRedirectToRoute('home');
});
