<?php

declare(strict_types=1);

use App\Actions\UpdateUserOnboardedAt;
use App\Models\User;

it('marks the user as onboarded', function (): void {
    $user = User::factory()->notOnboarded()->create();

    $action = resolve(UpdateUserOnboardedAt::class);

    $this->freezeSecond();

    $action->handle($user);

    expect($user->refresh()->onboarded_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('keeps when the user first onboarded', function (): void {
    $user = User::factory()->create(['onboarded_at' => now()->subWeek()]);
    $onboardedAt = $user->onboarded_at;

    $action = resolve(UpdateUserOnboardedAt::class);

    $action->handle($user);

    expect($user->refresh()->onboarded_at?->toDateTimeString())->toBe($onboardedAt?->toDateTimeString());
});
