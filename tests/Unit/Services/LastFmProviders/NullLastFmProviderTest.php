<?php

declare(strict_types=1);

use App\Contracts\Services\LastFmProvider;
use App\Services\LastFmProviders\NullLastFmProvider;

it('is the last.fm provider in testing', function (): void {
    expect(resolve(LastFmProvider::class))->toBeInstanceOf(NullLastFmProvider::class);
});

it('knows everyone, who are never listening to anything', function (): void {
    $provider = new NullLastFmProvider();

    expect($provider->exists('rj'))->toBeTrue()
        ->and($provider->nowPlaying('rj'))->toBeNull();
});
