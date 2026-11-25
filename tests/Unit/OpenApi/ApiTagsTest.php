<?php

declare(strict_types=1);

use App\OpenApi\ApiTags;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;

it('groups an endpoint by the first segment of its path', function (string $uri, string $tag): void {
    expect(new ApiTags()(new RouteInfo(new Route(['GET'], $uri, []), 'get')))->toBe([$tag]);
})->with([
    ['api/v1/users/{user}', 'Users'],
    ['api/v1/log/{log}', 'Me'],
    ['api/v1/starred', 'Me'],
]);

it('says where to add a group the docs do not have yet', function (): void {
    new ApiTags()(new RouteInfo(new Route(['GET'], 'api/v1/widgets', []), 'get'));
})->throws(LogicException::class, 'The API docs have no group for /widgets: add it to App\Enums\ApiDocsSection, or alias it in App\OpenApi\ApiTags.');
