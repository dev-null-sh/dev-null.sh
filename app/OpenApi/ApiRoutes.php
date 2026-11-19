<?php

declare(strict_types=1);

namespace App\OpenApi;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * The routes the API docs describe: every v1 endpoint, except those only the apps use to work, such as signing in,
 * the Mac app's update check, live updates and the signed URLs of remote images and voice notes. They are left out by
 * name, so a route moved to another path stays out.
 */
final readonly class ApiRoutes
{
    private const array Excluded = [
        'api-mac-app.show',
        'api-device.store',
        'api-broadcast-authorization.store',
        'api-web-session.store',
        'api-message-image.show',
        'api-message-voice.show',
        'api-openapi.show',
    ];

    public function __invoke(Route $route): bool
    {
        return Str::is('api/v1/*', $route->uri()) && ! in_array($route->getName(), self::Excluded, true);
    }
}
