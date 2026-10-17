<?php

declare(strict_types=1);

use App\Models\User;

it('may choose a lane', function (int $lane): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-lane.update'), ['lane' => $lane]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Color saved.',
        ]);

    expect($user->refresh()->preferred_lane)->toBe($lane)
        ->and($user->lane())->toBe($lane);
})->with([0, 1, 2, 3, 4, 5]);

it('may go back to the automatic lane', function (): void {
    $user = User::factory()->create(['preferred_lane' => 3]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-lane.update'), ['lane' => null]);

    $response->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->preferred_lane)->toBeNull()
        ->and($user->lane())->toBe($user->automaticLane());
});

it('treats an empty lane as automatic', function (): void {
    $user = User::factory()->create(['preferred_lane' => 3]);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-lane.update'), ['lane' => '']);

    expect($user->refresh()->preferred_lane)->toBeNull();
});

it('rejects lanes outside the palette', function (mixed $lane): void {
    $user = User::factory()->create(['preferred_lane' => 2]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-lane.update'), ['lane' => $lane]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('lane');

    expect($user->refresh()->preferred_lane)->toBe(2);
})->with([-1, 6, 1.5, 'teal', '#ff0000', [[1]]]);

it('may choose a custom color', function (): void {
    $user = User::factory()->create(['preferred_lane' => 2]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-lane.update'), ['lane' => null, 'color' => '#1a2b3c']);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasNoErrors();

    expect($user->refresh()->lane_color)->toBe('#1a2b3c')
        ->and($user->preferred_lane)->toBeNull()
        ->and($user->lane())->toBe('#1a2b3c');
});

it('drops the custom color for a lane from the palette or the automatic one', function (?int $lane): void {
    $user = User::factory()->create(['lane_color' => '#1a2b3c']);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-lane.update'), ['lane' => $lane]);

    expect($user->refresh()->lane_color)->toBeNull()
        ->and($user->lane())->toBe($lane ?? $user->automaticLane());
})->with([1, null]);

it('rejects colors that are not six digit hex', function (mixed $color): void {
    $user = User::factory()->create(['lane_color' => '#1a2b3c']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-lane.update'), ['lane' => null, 'color' => $color]);

    $response->assertSessionHasErrors('color');

    expect($user->refresh()->lane_color)->toBe('#1a2b3c');
})->with(['#fff', '#1A2B3C', '#1a2b3c80', '1a2b3c', 'red', '#gggggg', '#1a2b3c;fill:red', [['#1a2b3c']]]);

it('rejects a color together with a lane from the palette', function (): void {
    $user = User::factory()->create(['preferred_lane' => 2]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-lane.update'), ['lane' => 1, 'color' => '#1a2b3c']);

    $response->assertSessionHasErrors('color');

    expect($user->refresh()->preferred_lane)->toBe(2)
        ->and($user->lane_color)->toBeNull();
});

it('requires the lane to be present', function (): void {
    $user = User::factory()->create(['preferred_lane' => 2]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-lane.update'));

    $response->assertSessionHasErrors('lane');

    expect($user->refresh()->preferred_lane)->toBe(2);
});

it('requires authentication', function (): void {
    $response = $this->put(route('user-lane.update'), ['lane' => 1]);

    $response->assertRedirectToRoute('home');
});
