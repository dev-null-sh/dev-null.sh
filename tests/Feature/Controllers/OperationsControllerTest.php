<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('gives admins an overview of where the app is failing, what happened, and where it is heading', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));
    $admin = User::factory()->create(['username' => 'admin', 'created_at' => now()->subWeek()]);
    User::factory()->create(['username' => 'newcomer', 'created_at' => now()->subHour()]);
    failedJob(failedAt: now()->subDays(2));

    $response = $this->actingAs($admin)
        ->get(route('operations.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/show')
            ->has('signals', 10)
            ->where('signals.0.status', 'ok')
            ->has('daily', 28)
            ->where('daily.0.date', '2026-09-23')
            ->where('daily.25', ['date' => '2026-10-18', 'signups' => 0, 'commits' => 0, 'replies' => 0, 'active_users' => 0, 'stars' => 0, 'follows' => 0, 'failed_jobs' => 1])
            ->where('daily.27.signups', 1)
            ->has('forecasts', 4)
            ->has('recentEvents', 1)
            ->where('recentEvents.0.actor', 'newcomer')
            ->where('hasTraffic', false));
});

it('shows only the 10 newest events of the last day', function (): void {
    $admin = User::factory()->create(['username' => 'admin', 'created_at' => now()->subWeek()]);
    User::factory()->count(11)->create(['created_at' => now()->subHour()]);
    User::factory()->create(['created_at' => now()->subDays(2)]);

    $response = $this->actingAs($admin)
        ->get(route('operations.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->has('recentEvents', 10));
});

it('offers the traffic tab when a Fathom token is configured', function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('operations.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hasTraffic', true));
});

it('is a 404 for non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('operations.show'));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $response = $this->get(route('operations.show'));

    $response->assertRedirectToRoute('home');
});
