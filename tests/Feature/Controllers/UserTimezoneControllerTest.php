<?php

declare(strict_types=1);

use App\Models\User;

it('may choose a timezone', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-timezone.update'), ['timezone' => 'Europe/Budapest']);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Timezone saved.',
        ]);

    expect($user->refresh()->timezone)->toBe('Europe/Budapest');
});

it('may opt in to showing the local time', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-timezone.update'), ['timezone' => 'Europe/Budapest', 'show_local_time' => true])
        ->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh())
        ->timezone->toBe('Europe/Budapest')
        ->show_local_time->toBeTrue();
});

it('may opt out of showing the local time', function (): void {
    $user = User::factory()->create(['timezone' => 'Europe/Budapest', 'show_local_time' => true]);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-timezone.update'), ['timezone' => 'Europe/Budapest', 'show_local_time' => false]);

    expect($user->refresh()->show_local_time)->toBeFalse();
});

it('never shows the local time without a timezone', function (): void {
    $user = User::factory()->create(['timezone' => 'Europe/Budapest', 'show_local_time' => true]);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-timezone.update'), ['timezone' => null, 'show_local_time' => true]);

    expect($user->refresh())
        ->timezone->toBeNull()
        ->show_local_time->toBeFalse();
});

it('rejects a show_local_time that is not a boolean', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-timezone.update'), ['timezone' => 'Europe/Budapest', 'show_local_time' => 'maybe'])
        ->assertSessionHasErrors('show_local_time');

    expect($user->refresh()->timezone)->toBeNull();
});

it('may clear the timezone', function (mixed $timezone): void {
    $user = User::factory()->create(['timezone' => 'Europe/Budapest']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-timezone.update'), ['timezone' => $timezone]);

    $response->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->timezone)->toBeNull();
})->with([null, '']);

it('rejects unknown timezones', function (mixed $timezone): void {
    $user = User::factory()->create(['timezone' => 'Europe/Budapest']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-timezone.update'), ['timezone' => $timezone]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('timezone');

    expect($user->refresh()->timezone)->toBe('Europe/Budapest');
})->with(['Mars/Olympus_Mons', 'GMT+2', 'europe/budapest', 42, [['Europe/Budapest']]]);

it('requires the timezone to be present', function (): void {
    $user = User::factory()->create(['timezone' => 'Europe/Budapest']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-timezone.update'));

    $response->assertSessionHasErrors('timezone');

    expect($user->refresh()->timezone)->toBe('Europe/Budapest');
});

it('requires authentication', function (): void {
    $response = $this->put(route('user-timezone.update'), ['timezone' => 'Europe/Budapest']);

    $response->assertRedirectToRoute('home');
});
