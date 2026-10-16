<?php

declare(strict_types=1);

use App\Services\Presence;

it('counts a user as online within the online window', function (): void {
    $presence = new Presence();

    expect($presence->isOnline(now()->subMinutes(Presence::ONLINE_WINDOW_MINUTES - 1)))->toBeTrue();
});

it('counts a user as offline once the online window has passed', function (): void {
    $presence = new Presence();

    expect($presence->isOnline(now()->subMinutes(Presence::ONLINE_WINDOW_MINUTES)))->toBeFalse()
        ->and($presence->isOnline(now()->subMinutes(Presence::ONLINE_WINDOW_MINUTES + 1)))->toBeFalse();
});

it('counts a user who was never active as offline', function (): void {
    expect(new Presence()->isOnline(null))->toBeFalse();
});

it('refreshes a timestamp once it is stale enough', function (): void {
    $presence = new Presence();

    expect($presence->needsRefresh(null))->toBeTrue()
        ->and($presence->needsRefresh(now()->subMinutes(Presence::REFRESH_AFTER_MINUTES)))->toBeTrue()
        ->and($presence->needsRefresh(now()->subMinutes(Presence::REFRESH_AFTER_MINUTES - 1)))->toBeFalse();
});

it('refreshes before the online window would expire', function (): void {
    expect(Presence::REFRESH_AFTER_MINUTES)->toBeLessThan(Presence::ONLINE_WINDOW_MINUTES);
});
