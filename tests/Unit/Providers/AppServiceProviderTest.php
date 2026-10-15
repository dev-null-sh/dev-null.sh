<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('only allows admins to moderate', function (): void {
    config(['app.admins' => ['admin']]);

    $admin = User::factory()->create(['username' => 'admin']);
    $user = User::factory()->create();

    expect(Gate::forUser($admin)->allows('moderate'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('moderate'))->toBeFalse();
});
