<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('shows the operational health of the app to admins', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    Commit::factory()->pending()->count(2)->create();
    Commit::factory()->for(Commit::factory(), 'parent')->create();
    LinkPreview::factory()->create();
    LinkPreview::factory()->failed()->create();
    queuedJob('default', now()->subMinutes(5));
    failedJob();

    $response = $this->actingAs($admin)
        ->get(route('operations.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/show')
            ->where('queues', [['queue' => 'default', 'size' => 1, 'oldest_job_age' => 300]])
            ->where('failedJobs.total', 1)
            ->has('failedJobs.recent', 1)
            ->where('connectivity', ['database' => true, 'cache' => true])
            ->where('linkPreviews', ['pending' => 1, 'failed' => 1])
            ->where('pendingModerationCount', 2)
            ->where('volume.signups', ['day' => 5, 'week' => 5])
            ->where('volume.commits', ['day' => 3, 'week' => 3])
            ->where('volume.replies', ['day' => 1, 'week' => 1])
            ->where('features', [['name' => 'translations', 'enabled' => true]])
            ->missing('githubRateLimit')
            ->missing('traffic'));
});

it('shows the GitHub rate limit when GitHub credentials are configured', function (): void {
    config(['services.github.client_id' => 'client-id', 'services.github.client_secret' => 'client-secret']);

    Http::fake([
        'api.github.com/rate_limit' => Http::response([
            'resources' => ['core' => ['limit' => 5000, 'remaining' => 4321, 'reset' => now()->addMinutes(30)->getTimestamp()]],
        ]),
    ]);

    $response = $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('operations.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('githubRateLimit')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('githubRateLimit.remaining', 4321)
                ->where('githubRateLimit.limit', 5000)));
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

it('shows Fathom traffic for the last seven days when a Fathom token is configured', function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);
    $this->travelTo(now()->setDate(2026, 10, 18));

    Http::fake([
        'api.usefathom.com/v1/current_visitors*' => Http::response(['total' => 3]),
        'api.usefathom.com/v1/aggregations*' => Http::response([['uniques' => '120', 'pageviews' => '480']]),
    ]);

    $response = $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('operations.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trafficRange', ['from' => '2026-10-12', 'to' => '2026-10-18'])
            ->missing('traffic')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('traffic.current', 3)
                ->where('traffic.totals.visitors', 120)
                ->where('traffic.totals.pageviews', 480)));
});

it('shows Fathom traffic for the chosen dates', function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);

    Http::fake([
        'api.usefathom.com/v1/current_visitors*' => Http::response(['total' => 3]),
        'api.usefathom.com/v1/aggregations*' => Http::response([]),
    ]);

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('operations.show', ['from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trafficRange', ['from' => '2026-01-01', 'to' => '2026-01-31'])
            ->loadDeferredProps(fn ($reload) => $reload->where('traffic.current', 3)));

    Http::assertSent(fn (Request $request): bool => ($request->data()['date_from'] ?? null) === '2026-01-01 00:00:00'
        && ($request->data()['date_to'] ?? null) === '2026-01-31 23:59:59');
});

it('rejects traffic dates that are malformed, in the future, or out of order', function (array $query): void {
    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('operations.show', $query))
        ->assertSessionHasErrors();
})->with([
    'malformed' => [['from' => '18/10/2026']],
    'in the future' => [['to' => now()->addDay()->toDateString()]],
    'out of order' => [['from' => '2026-02-01', 'to' => '2026-01-01']],
]);
