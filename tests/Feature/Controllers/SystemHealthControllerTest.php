<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('shows the operational health of the app to admins', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);

    Commit::factory()->pending()->count(2)->create(['created_at' => now()->subMinutes(10)]);
    LinkPreview::factory()->create();
    LinkPreview::factory()->failed()->create();
    queuedJob('default', now()->subMinutes(5));
    failedJob();

    $response = $this->actingAs($admin)
        ->get(route('system-health.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/health')
            ->where('queues', [['queue' => 'default', 'size' => 1, 'oldest_job_age' => 300]])
            ->where('failedJobs.total', 1)
            ->has('failedJobs.recent', 1)
            ->where('failureTrend.last_24h', 1)
            ->has('failureTrend.hours', 24)
            ->has('failureTrend.by_job', 1)
            ->where('connectivity', ['database' => true, 'cache' => true])
            ->where('linkPreviews', ['pending' => 1, 'failed' => 1])
            ->where('moderation', ['pending' => 2, 'oldest_pending_age' => 600, 'open_reports' => 0, 'active_timeouts' => 0])
            ->where('runtime', [
                'environment' => 'testing',
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'database' => 'pgsql',
                'cache' => 'array',
                'queue' => 'database',
            ])
            ->missing('githubRateLimit'));
});

it('shows the GitHub rate limit', function (): void {
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('rateLimit')->andReturn(['remaining' => 4321, 'limit' => 5000, 'resets_at' => now()->addMinutes(30)->toImmutable()]);

    $response = $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('system-health.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('githubRateLimit')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('githubRateLimit.remaining', 4321)
                ->where('githubRateLimit.limit', 5000)));
});

it('is a 404 for non admins', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('system-health.show'));

    $response->assertNotFound();
});

it('requires authentication', function (): void {
    $response = $this->get(route('system-health.show'));

    $response->assertRedirectToRoute('home');
});
