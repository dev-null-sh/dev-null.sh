<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:30:00'));
});

it('lists the events of the last 24 hours to admins by default', function (): void {
    $admin = User::factory()->create(['username' => 'admin', 'created_at' => now()->subWeek()]);
    User::factory()->create(['username' => 'newcomer', 'created_at' => now()->subHour()]);
    failedJob(failedAt: now()->subMinutes(10));
    failedJob(failedAt: now()->subDays(2));

    $response = $this->actingAs($admin)
        ->get(route('operations-event.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/events')
            ->has('events', 2)
            ->where('events.0.source', 'jobs')
            ->where('events.1.actor', 'newcomer')
            ->where('filters', ['window' => '24h', 'source' => null, 'level' => null, 'search' => ''])
            ->where('counts', ['signups' => 1, 'jobs' => 1, 'reports' => 0, 'moderation' => 0, 'bans' => 0, 'timeouts' => 0, 'features' => 0])
            ->has('histogram', 24)
            ->where('histogram.23', ['at' => '2026-10-20T12:00:00+00:00', 'info' => 0, 'notice' => 0, 'warning' => 0, 'error' => 1])
            ->where('hasTraffic', false));
});

it('filters the events by window, source, level and search', function (): void {
    $admin = User::factory()->create(['username' => 'admin', 'created_at' => now()->subMonth()]);
    User::factory()->create(['username' => 'early_bird', 'created_at' => now()->subDays(5)]);
    User::factory()->create(['username' => 'night_owl', 'created_at' => now()->subDays(5)]);
    failedJob(failedAt: now()->subDays(5));

    $response = $this->actingAs($admin)
        ->get(route('operations-event.index', ['window' => '7d', 'source' => 'signups', 'level' => 'info', 'search' => ' early ']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.actor', 'early_bird')
            ->where('filters', ['window' => '7d', 'source' => 'signups', 'level' => 'info', 'search' => 'early'])
            ->where('counts.signups', 1)
            ->where('counts.jobs', 0)
            ->has('histogram', 28));
});

it('rejects unknown filters', function (array $query, string $field): void {
    $response = $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('operations-event.index', $query));

    $response->assertRedirect()
        ->assertSessionHasErrors($field);
})->with([
    'window' => [['window' => '2d'], 'window'],
    'source' => [['source' => 'logins'], 'source'],
    'level' => [['level' => 'debug'], 'level'],
    'search' => [['search' => str_repeat('a', 101)], 'search'],
]);

it('is a 404 for non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('operations-event.index'));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $response = $this->get(route('operations-event.index'));

    $response->assertRedirectToRoute('home');
});
