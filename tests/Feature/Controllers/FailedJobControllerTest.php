<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may forget a failed job', function (): void {
    $uuid = failedJob();
    failedJob();

    $response = $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->fromRoute('operations.show')
        ->delete(route('failed-job.destroy', $uuid));

    $response->assertRedirectToRoute('operations.show');

    expect(DB::table('failed_jobs')->pluck('uuid')->all())->not->toContain($uuid)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('is a 404 for non admins', function (): void {
    $uuid = failedJob();

    $response = $this->actingAs(User::factory()->create())
        ->delete(route('failed-job.destroy', $uuid));

    $response->assertNotFound();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});
