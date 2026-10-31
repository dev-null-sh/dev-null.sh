<?php

declare(strict_types=1);

use App\Actions\CreateOrganizationOpenGraphImage;
use App\Actions\CreateUserOpenGraphImage;
use App\Models\Organization;
use App\Models\User;
use App\Services\OpenGraphCanvas;

it('draws the avatar of an organization square, where a person is round', function (): void {
    $organization = Organization::factory()->create(['avatar_url' => null, 'bio' => null]);
    $user = User::factory()->create(['avatar_url' => null, 'bio' => null]);

    $organizationImage = imagecreatefromstring(resolve(CreateOrganizationOpenGraphImage::class)->handle($organization));
    $userImage = imagecreatefromstring(resolve(CreateUserOpenGraphImage::class)->handle($user));

    expect($organizationImage)->toBeInstanceOf(GdImage::class)
        ->and($userImage)->toBeInstanceOf(GdImage::class)
        ->and(imagecolorat($organizationImage, 204, 162))->toBe(OpenGraphCanvas::PRIMARY)
        ->and(imagecolorat($userImage, 204, 162))->toBe(0xF6F7F8);
});

it('draws the rail in the blue of /dev/null while the admins chose no lane', function (): void {
    $organization = Organization::factory()->create(['avatar_url' => null, 'bio' => null]);

    $image = imagecreatefromstring(resolve(CreateOrganizationOpenGraphImage::class)->handle($organization));

    expect($image)->toBeInstanceOf(GdImage::class)
        ->and(imagecolorat($image, OpenGraphCanvas::RAIL_X, 440))->toBe(OpenGraphCanvas::PRIMARY);
});

it('draws the rail in the lane the admins chose, drawing it again once they change it', function (): void {
    $organization = Organization::factory()->create(['avatar_url' => null, 'bio' => null, 'preferred_lane' => 1]);
    $action = resolve(CreateOrganizationOpenGraphImage::class);

    $palette = imagecreatefromstring($action->handle($organization));
    $organization->update(['preferred_lane' => null, 'lane_color' => '#1a2b3c']);
    $custom = imagecreatefromstring($action->handle($organization));

    expect($palette)->toBeInstanceOf(GdImage::class)
        ->and($custom)->toBeInstanceOf(GdImage::class)
        ->and(imagecolorat($palette, OpenGraphCanvas::RAIL_X, 440))->toBe(0x2F9E44)
        ->and(imagecolorat($custom, OpenGraphCanvas::RAIL_X, 440))->toBe(0x1A2B3C);
});

it('fits descriptions that would overflow the card', function (): void {
    $organization = Organization::factory()->create([
        'avatar_url' => null,
        'bio' => str_repeat('The PHP framework for web artisans, shipping before lunch. ', 4),
    ]);

    $image = resolve(CreateOrganizationOpenGraphImage::class)->handle($organization);

    expect(array_slice((array) getimagesizefromstring($image), 0, 2))->toBe([1200, 630]);
});
