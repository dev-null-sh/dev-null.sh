<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['app.admins' => ['admin'], 'services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);
});

it('shows Fathom traffic for the last seven days to admins', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 18));

    Http::fake([
        'api.usefathom.com/v1/current_visitors*' => Http::response(['total' => 3]),
        'api.usefathom.com/v1/aggregations*' => Http::response([['uniques' => '120', 'pageviews' => '480']]),
    ]);

    $response = $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('traffic.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/traffic')
            ->where('trafficRange', ['from' => '2026-10-12', 'to' => '2026-10-18'])
            ->where('hasTraffic', true)
            ->missing('traffic')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('traffic.current', 3)
                ->where('traffic.totals.visitors', 120)
                ->where('traffic.totals.pageviews', 480)));
});

it('shows Fathom traffic for the chosen dates', function (): void {
    Http::fake([
        'api.usefathom.com/v1/current_visitors*' => Http::response(['total' => 3]),
        'api.usefathom.com/v1/aggregations*' => Http::response([]),
    ]);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('traffic.show', ['from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trafficRange', ['from' => '2026-01-01', 'to' => '2026-01-31'])
            ->loadDeferredProps(fn ($reload) => $reload->where('traffic.current', 3)));

    Http::assertSent(fn (Request $request): bool => ($request->data()['date_from'] ?? null) === '2026-01-01 00:00:00'
        && ($request->data()['date_to'] ?? null) === '2026-01-31 23:59:59');
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

it('is a 404 without a Fathom token', function (): void {
    config(['services.fathom.token' => null]);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('traffic.show'))
        ->assertNotFound();
});

it('is a 404 for non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('traffic.show'));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $response = $this->get(route('traffic.show'));

    $response->assertRedirectToRoute('home');
});
