<?php

declare(strict_types=1);

use App\Actions\CreateAuthorizationCode;
use App\Dto\AuthorizationCode;
use App\Dto\MobileAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('hands out a one-time code bound to what the user approved, and only keeps it under its hash for a few minutes', function (): void {
    $user = User::factory()->create();
    $authorization = new MobileAuthorization('SHA256:key', 'devnull on iPhone', 'challenge', 'state', 'devnull://auth/callback');

    $code = resolve(CreateAuthorizationCode::class)->handle($user, $authorization);

    expect($code)->toMatch('/^[0-9a-f]{64}$/')
        ->and(Cache::get(AuthorizationCode::keyFor($code)))->toBe([
            'user_id' => $user->id,
            'fingerprint' => 'SHA256:key',
            'device_name' => 'devnull on iPhone',
            'code_challenge' => 'challenge',
        ]);

    $this->travel(AuthorizationCode::EXPIRES_AFTER_MINUTES)->minutes();

    expect(Cache::has(AuthorizationCode::keyFor($code)))->toBeFalse();
});

it('hands out a different code every time', function (): void {
    $user = User::factory()->create();
    $authorization = new MobileAuthorization('SHA256:key', 'devnull on iPhone', 'challenge', 'state', 'devnull://auth/callback');

    $first = resolve(CreateAuthorizationCode::class)->handle($user, $authorization);
    $second = resolve(CreateAuthorizationCode::class)->handle($user, $authorization);

    expect($first)->not->toBe($second)
        ->and(Cache::has(AuthorizationCode::keyFor($first)))->toBeTrue()
        ->and(Cache::has(AuthorizationCode::keyFor($second)))->toBeTrue();
});
