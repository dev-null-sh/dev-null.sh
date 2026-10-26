<?php

declare(strict_types=1);

use App\Services\AssetVersion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->sandbox = storage_path('framework/testing/asset-version-'.Str::uuid());
    File::ensureDirectoryExists($this->sandbox.'/build');
    app()->usePublicPath($this->sandbox);
    config(['app.asset_url' => null]);
});

afterEach(function (): void {
    File::deleteDirectory($this->sandbox);
});

it('prefers the asset URL over the manifests', function (): void {
    config(['app.asset_url' => 'https://assets.example.com']);
    File::put(public_path('build/manifest.json'), '{}');

    expect(resolve(AssetVersion::class)->get())->toBe(hash('xxh128', 'https://assets.example.com'));
});

it('hashes the Vite manifest before the Mix manifest and reads changes', function (): void {
    File::put(public_path('build/manifest.json'), '{"version":1}');
    File::put(public_path('mix-manifest.json'), '{}');

    $version = resolve(AssetVersion::class);

    expect($version->get())->toBe(hash('xxh128', '{"version":1}'));

    File::put(public_path('build/manifest.json'), '{"version":2}');

    expect($version->get())->toBe(hash('xxh128', '{"version":2}'));
});

it('falls back to the Mix manifest', function (): void {
    File::put(public_path('mix-manifest.json'), '{}');

    expect(resolve(AssetVersion::class)->get())->toBe(hash('xxh128', '{}'));
});

it('has no version when there is no asset URL or manifest', function (): void {
    expect(resolve(AssetVersion::class)->get())->toBeNull();
});
