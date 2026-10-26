<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may forget every failed job', function (): void {
    failedJob();
    failedJob();

    $response = $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->fromRoute('operations.show')
        ->delete(route('failed-jobs.destroy'));

    $response->assertRedirectToRoute('operations.show');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('is a 404 for non admins', function (): void {
    failedJob();

    $response = $this->actingAs(User::factory()->create())
        ->delete(route('failed-jobs.destroy'));

    $response->assertNotFound();

    expect(DB::table('failed_jobs')->count())->toBe(1);
});
