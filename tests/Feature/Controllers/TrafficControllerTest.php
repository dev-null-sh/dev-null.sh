<?php

declare(strict_types=1);

use App\Contracts\Services\AnalyticsProvider;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('shows the traffic for the last seven days to admins', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 18));

    $this->mock(AnalyticsProvider::class)->shouldReceive('traffic')
        ->withArgs(fn (CarbonImmutable $from, CarbonImmutable $to): bool => $from->toDateString() === '2026-10-12' && $to->toDateString() === '2026-10-18')
        ->andReturn([
            'current' => 3,
            'totals' => ['visitors' => 120, 'pageviews' => 480, 'avg_duration' => 0, 'bounce_rate' => 0.0],
            'breakdowns' => ['pages' => [], 'referrers' => [], 'countries' => [], 'devices' => [], 'browsers' => []],
        ]);

    $response = $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('traffic.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/traffic')
            ->where('trafficRange', ['from' => '2026-10-12', 'to' => '2026-10-18'])
            ->missing('traffic')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('traffic.current', 3)
                ->where('traffic.totals.visitors', 120)
                ->where('traffic.totals.pageviews', 480)));
});

it('shows the traffic for the chosen dates', function (): void {
    $this->mock(AnalyticsProvider::class)->shouldReceive('traffic')
        ->withArgs(fn (CarbonImmutable $from, CarbonImmutable $to): bool => $from->toDateString() === '2026-01-01' && $to->toDateString() === '2026-01-31')
        ->andReturnNull();

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('traffic.show', ['from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trafficRange', ['from' => '2026-01-01', 'to' => '2026-01-31'])
            ->loadDeferredProps(fn ($reload) => $reload->where('traffic', null)));
});

it('rejects dates that are malformed, in the future, or out of order', function (array $query): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('traffic.show', $query))
        ->assertSessionHasErrors();
})->with([
    'malformed' => [['from' => '18/10/2026']],
    'in the future' => [['to' => now()->addDay()->toDateString()]],
    'out of order' => [['from' => '2026-02-01', 'to' => '2026-01-01']],
]);

it('is a 404 for non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('traffic.show'));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $response = $this->get(route('traffic.show'));

    $response->assertRedirectToRoute('home');
});
