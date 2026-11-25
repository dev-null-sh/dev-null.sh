<?php

declare(strict_types=1);

use App\OpenApi\PathParameters;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;

it('refuses to name two path parameters alike', function (): void {
    $operation = Operation::make('get');
    $operation->setPath('/users/{follower}/follows/{followed}');

    new PathParameters()->handle($operation, new RouteInfo(new Route(['GET'], 'api/v1/users/{follower:handle}/follows/{followed:handle}', []), 'get'));
})->throws(LogicException::class, 'Two path parameters of /users/{follower}/follows/{followed} would both be named {handle}.');
