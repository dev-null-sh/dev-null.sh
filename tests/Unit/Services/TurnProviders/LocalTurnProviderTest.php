<?php

declare(strict_types=1);

use App\Contracts\Services\TurnProvider;
use App\Services\TurnProviders\LocalTurnProvider;

it('is the turn provider in testing', function (): void {
    expect(resolve(TurnProvider::class))->toBeInstanceOf(LocalTurnProvider::class);
});

it('lets browsers connect directly, with no relay to configure', function (): void {
    $provider = new LocalTurnProvider();

    expect($provider->isRelayOnly())->toBeFalse()
        ->and($provider->iceServers())->toBe([]);
});
