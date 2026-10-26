<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->withoutVite();
    $this->app->usePublicPath(storage_path('framework/testing/public'));
    File::ensureDirectoryExists(public_path('downloads'));
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('framework/testing/public'));
});

it('answers the update check with the published release, unsigned', function (): void {
    File::put(public_path('downloads/DevNull.dmg'), 'dmg');
    File::put(public_path('downloads/DevNull.json'), json_encode([
        'version' => '1.1.0',
        'build' => 1200,
        'revision' => 'b1c2d3e4',
        'released_at' => '2026-11-02T10:00:00Z',
        'minimum_system_version' => '14.0',
        'notarized' => true,
        'size' => 4000000,
        'sha256' => str_repeat('cd', 32),
    ]));

    $this->getJson(route('api-mac-app.show'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'version' => '1.1.0',
            'build' => 1200,
            'revision' => 'b1c2d3e4',
            'released_at' => '2026-11-02T10:00:00Z',
            'minimum_system_version' => '14.0',
            'notarized' => true,
            'size' => 4000000,
            'sha256' => str_repeat('cd', 32),
            'download_url' => url('downloads/DevNull.dmg'),
            'url' => route('app.index'),
        ]])
        ->assertCookieMissing(config()->string('session.cookie'));
});

it('answers null until a release is published', function (): void {
    $this->getJson(route('api-mac-app.show'))
        ->assertOk()
        ->assertExactJson(['data' => null]);
});
