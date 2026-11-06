<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\User;

it('keeps the web session of a device the user still has', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->withSession([Device::SessionKey => $device->id])
        ->get(route('device.index'))
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

it('ends the web session of a revoked device', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $id = $device->id;
    $device->delete();

    $this->actingAs($user)
        ->withSession([Device::SessionKey => $id])
        ->get(route('device.index'))
        ->assertRedirect();

    $this->assertGuest();

    expect(session(Device::SessionKey))->toBeNull();
});

it('ends the web session of a device whose key was approved for someone else', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user)
        ->withSession([Device::SessionKey => $device->id])
        ->get(route('device.index'))
        ->assertRedirect();

    $this->assertGuest();
});

it('leaves other web sessions alone', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('device.index'))
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});
