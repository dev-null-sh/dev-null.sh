<?php

declare(strict_types=1);

use App\OpenApi\ApiRoutes;
use Illuminate\Routing\Route;

it('describes every v1 endpoint', function (): void {
    expect(new ApiRoutes()(new Route(['GET'], 'api/v1/users/{user}', [])->name('api-user.show')))->toBeTrue()
        ->and(new ApiRoutes()(new Route(['GET'], 'users/{user}', [])->name('user.show')))->toBeFalse();
});

it('leaves out what only the apps use to work, wherever it moves', function (): void {
    expect(new ApiRoutes()(new Route(['POST'], 'api/v1/devices', [])->name('api-device.store')))->toBeFalse()
        ->and(new ApiRoutes()(new Route(['GET'], 'api/v1/media/images/{messageImage}', [])->name('api-message-image.show')))->toBeFalse()
        ->and(new ApiRoutes()(new Route(['GET'], 'api/v1/guilds/{guild}/icon', [])->name('api-guild-icon.show')))->toBeFalse();
});
