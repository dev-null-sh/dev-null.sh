<?php

declare(strict_types=1);

use App\Contracts\Services\ModerationProvider;
use App\Services\ModerationProviders\NullModerationProvider;

it('is the moderation provider in testing', function (): void {
    expect(resolve(ModerationProvider::class))->toBeInstanceOf(NullModerationProvider::class);
});

it('lets everything through', function (): void {
    $provider = new NullModerationProvider();

    expect($provider->moderate('I have a bomb', ['data:image/webp;base64,'.base64_encode('first')]))->toBe([])
        ->and($provider->isHarmful('I have a bomb'))->toBeFalse();
});
