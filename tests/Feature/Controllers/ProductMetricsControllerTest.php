<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);

    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(12, 0));
});

it('shows the product metrics of all time to admins', function (): void {
    $admin = User::factory()->create(['handle' => 'admin', 'created_at' => '2026-10-16 09:00:00']);
    User::factory()->create(['created_at' => '2026-10-17 09:00:00', 'last_seen_at' => '2026-10-19']);
    DB::table('active_user_counts')->insert(['seen_at' => '2026-10-17', 'count' => 7]);

    $this->actingAs($admin)
        ->get(route('product-metrics.show', ['all_time' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/metrics')
            ->where('range', ['from' => '2026-10-16', 'to' => '2026-10-19', 'all_time' => true])
            ->where('activeUsers.daily', 1)
            ->where('responses.commits', 0)
            ->where('newcomerFollows.newcomers', 2)
            ->where('activeUserCounts', [['date' => '2026-10-17', 'count' => 7, 'returning' => 6, 'activated' => 0, 'not_activated' => 1, 'pending' => 0]])
            ->where('hasTraffic', false)
            ->missing('funnel')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('funnel.signups', 2)
                ->where('funnel.retention_eligible', 2)
                ->where('funnel.visitors', null)));
});

it('shows the product metrics of the last 30 days by default', function (): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin', 'created_at' => '2026-01-01 09:00:00']))
        ->get(route('product-metrics.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('range', ['from' => '2026-09-20', 'to' => '2026-10-19', 'all_time' => false]));
});

it('shows the product metrics of the chosen dates', function (): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('product-metrics.show', ['from' => '2026-10-01', 'to' => '2026-10-10']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('range', ['from' => '2026-10-01', 'to' => '2026-10-10', 'all_time' => false]));
});

it('rejects dates that are malformed, in the future, or out of order', function (array $query): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('product-metrics.show', $query))
        ->assertSessionHasErrors();
})->with([
    'malformed' => [['from' => '18/10/2026']],
    'in the future' => [['to' => '2026-10-20']],
    'out of order' => [['from' => '2026-02-01', 'to' => '2026-01-01']],
    'only a start' => [['from' => '2026-01-01']],
    'only an end' => [['to' => '2026-01-01']],
    'all time with dates' => [['all_time' => 1, 'from' => '2026-01-01', 'to' => '2026-01-02']],
]);

it('is a 404 for non admins', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('product-metrics.show'))
        ->assertNotFound();
});

it('requires authentication', function (): void {
    $this->get(route('product-metrics.show'))->assertRedirect(route('home'));
});
