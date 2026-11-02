<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may retry a failed job', function (): void {
    $uuid = failedJob(queue: 'previews');

    $response = $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->fromRoute('operations.show')
        ->post(route('failed-job-retry.store', $uuid));

    $response->assertRedirectToRoute('operations.show');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->where('queue', 'previews')->count())->toBe(1);
});

it('is a 404 for non admins', function (): void {
    $uuid = failedJob();

    $response = $this->actingAs(User::factory()->create())
        ->post(route('failed-job-retry.store', $uuid));

    $response->assertNotFound();

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(0);
});
