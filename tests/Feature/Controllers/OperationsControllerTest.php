<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('shows the operational health of the app to admins', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    Commit::factory()->pending()->count(2)->create();
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
            ->where('hasTraffic', false)
            ->missing('githubRateLimit'));
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
