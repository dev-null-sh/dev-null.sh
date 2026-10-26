<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('draws the activity of the site to admins', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);

    Commit::factory()->pending()->count(2)->create();
    Commit::factory()->for(Commit::factory(), 'parent')->create();

    $response = $this->actingAs($admin)
        ->get(route('site-activity.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/activity')
            ->where('today.signups', ['today' => 5, 'yesterday' => 0])
            ->where('today.commits', ['today' => 3, 'yesterday' => 0])
            ->where('today.replies', ['today' => 1, 'yesterday' => 0])
            ->where('today.authors', ['today' => 4, 'yesterday' => 0])
            ->where('overall.since', today()->toDateString())
            ->where('overall.signups', 5)
            ->where('overall.commits', 3)
            ->where('overall.replies', 1)
            ->where('overall.authors', 4)
            ->where('activity.metric', 'commits')
            ->where('activity.scale', 'week')
            ->has('activity.buckets', 8)
            ->where('activity.buckets.7.count', 3)
            ->where('breakdowns.statuses', [
                ['name' => 'approved', 'count' => 2],
                ['name' => 'pending', 'count' => 2],
                ['name' => 'rejected', 'count' => 0],
            ])
            ->has('breakdowns.authors', 4)
            ->where('hasTraffic', false));
});

it('draws the activity at the chosen scale', function (): void {
    $response = $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('site-activity.show', ['scale' => 'day']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activity.scale', 'day')
            ->has('activity.buckets', 25));
});

it('draws the chosen series', function (): void {
    User::factory()->create(['created_at' => now()->subDay()]);

    $response = $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('site-activity.show', ['metric' => 'signups']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activity.metric', 'signups')
            ->where('activity.buckets.6.count', 1)
            ->where('activity.buckets.7.count', 1));
});

it('offers the traffic tab when a Fathom token is configured', function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('site-activity.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hasTraffic', true));
});

it('rejects unknown activity scales and series', function (array $query): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('site-activity.show', $query))
        ->assertSessionHasErrors();
})->with([
    'unknown scale' => [['scale' => 'decade']],
    'unknown series' => [['metric' => 'stars']],
]);

it('is a 404 for non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('site-activity.show'));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $response = $this->get(route('site-activity.show'));

    $response->assertRedirectToRoute('home');
});
