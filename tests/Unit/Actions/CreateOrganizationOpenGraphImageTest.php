<?php

declare(strict_types=1);

use App\Actions\CreateOrganizationOpenGraphImage;
use App\Actions\CreateUserOpenGraphImage;
use App\Models\Profile;
use App\Models\User;
use App\Services\OpenGraphCanvas;

it('draws the avatar of an organization square, where a person is round', function (): void {
    $organization = Profile::factory()->organization()->create(['avatar_url' => null, 'bio' => null]);
    $user = User::factory()->create(['avatar_url' => null, 'bio' => null]);

    $organizationImage = imagecreatefromstring(resolve(CreateOrganizationOpenGraphImage::class)->handle($organization));
    $userImage = imagecreatefromstring(resolve(CreateUserOpenGraphImage::class)->handle($user));

    expect($organizationImage)->toBeInstanceOf(GdImage::class)
        ->and($userImage)->toBeInstanceOf(GdImage::class)
        ->and(imagecolorat($organizationImage, 204, 162))->toBe(OpenGraphCanvas::INK_MUTED)
        ->and(imagecolorat($userImage, 204, 162))->toBe(0xF6F7F8);
});

it('draws the rail in the muted color, since organizations have no lane', function (): void {
    $organization = Profile::factory()->organization()->create(['avatar_url' => null, 'bio' => null]);

    $image = imagecreatefromstring(resolve(CreateOrganizationOpenGraphImage::class)->handle($organization));

    expect($image)->toBeInstanceOf(GdImage::class)
        ->and(imagecolorat($image, OpenGraphCanvas::RAIL_X, 440))->toBe(OpenGraphCanvas::INK_MUTED);
});

it('fits descriptions that would overflow the card', function (): void {
    $organization = Profile::factory()->organization()->create([
        'avatar_url' => null,
        'bio' => str_repeat('The PHP framework for web artisans, shipping before lunch. ', 4),
    ]);

    $image = resolve(CreateOrganizationOpenGraphImage::class)->handle($organization);

    expect(array_slice((array) getimagesizefromstring($image), 0, 2))->toBe([1200, 630]);
});
