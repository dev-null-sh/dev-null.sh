<?php

declare(strict_types=1);

use App\Actions\UpdateUserLastActiveAt;
use App\Http\Middleware\TouchUserPresence;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('marks an authenticated user as active', function (): void {
    $user = User::factory()->create(['last_activity_at' => null]);

    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn (): User => $user);

    $middleware = new TouchUserPresence(resolve(UpdateUserLastActiveAt::class));

    $response = $middleware->handle($request, fn ($request): Response => response('OK'));

    expect($response->getContent())->toBe('OK')
        ->and($user->refresh()->last_activity_at)->not->toBeNull();
});

it('leaves guests alone', function (): void {
    $middleware = new TouchUserPresence(resolve(UpdateUserLastActiveAt::class));

    $response = $middleware->handle(Request::create('/', 'GET'), fn ($request): Response => response('OK'));

    expect($response->getContent())->toBe('OK');
});
