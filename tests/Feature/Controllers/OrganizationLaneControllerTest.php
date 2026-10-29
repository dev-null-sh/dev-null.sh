<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\User;

/**
 * @return array{0: User, 1: Profile}
 */
function organizationAdmin(array $attributes = []): array
{
    $user = User::factory()->create();
    $organization = Profile::factory()->organization()->create(['handle' => 'laravel', ...$attributes]);
    $organization->admins()->attach($user->id, ['created_at' => now()]);

    return [$user, $organization];
}

it('lets an admin choose the lane of the organization', function (array $input, ?int $lane, ?string $color): void {
    [$user, $organization] = organizationAdmin(['preferred_lane' => 2, 'lane_color' => null]);

    $this->actingAs($user)
        ->from('/@laravel')
        ->put(route('organization-lane.update', $organization), $input)
        ->assertRedirect('/@laravel')
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Color saved.']);

    expect($organization->refresh()->preferred_lane)->toBe($lane)
        ->and($organization->lane_color)->toBe($color);
})->with([
    'from the palette' => [['lane' => 5], 5, null],
    'custom' => [['lane' => null, 'color' => '#1a2b3c'], null, '#1a2b3c'],
    'none' => [['lane' => ''], null, null],
]);

it('drops the custom color for a lane from the palette', function (): void {
    [$user, $organization] = organizationAdmin(['lane_color' => '#1a2b3c']);

    $this->actingAs($user)->put(route('organization-lane.update', $organization), ['lane' => 1]);

    expect($organization->refresh()->lane())->toBe(1)
        ->and($organization->lane_color)->toBeNull();
});

it('rejects lanes and colors outside what users may choose', function (array $input, string $error): void {
    [$user, $organization] = organizationAdmin(['preferred_lane' => 2]);

    $this->actingAs($user)
        ->put(route('organization-lane.update', $organization), $input)
        ->assertSessionHasErrors($error);

    expect($organization->refresh()->lane())->toBe(2);
})->with([
    'outside the palette' => [['lane' => 6], 'lane'],
    'short hex' => [['lane' => null, 'color' => '#fff'], 'color'],
    'injected style' => [['lane' => null, 'color' => '#1a2b3c;fill:red'], 'color'],
    'both' => [['lane' => 1, 'color' => '#1a2b3c'], 'color'],
    'missing lane' => [[], 'lane'],
]);

it('hides the color from users who are not admins of the organization', function (): void {
    [, $organization] = organizationAdmin(['preferred_lane' => 2]);
    $member = User::factory()->create();

    $this->actingAs($member)
        ->put(route('organization-lane.update', $organization), ['lane' => 1])
        ->assertNotFound();

    expect($organization->refresh()->lane())->toBe(2);
});

it('hides the color of profiles of users, which have no admins', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('organization-lane.update', $user->profile), ['lane' => 1])
        ->assertNotFound();

    expect($user->profile->refresh()->lane())->toBeNull();
});

it('requires authentication', function (): void {
    [, $organization] = organizationAdmin();

    $this->put(route('organization-lane.update', $organization), ['lane' => 1])
        ->assertRedirectToRoute('home');
});
