<?php

declare(strict_types=1);

use App\Omarchy\OmarchyTheme;
use App\Omarchy\ResolveOmarchyTheme;

beforeEach(function (): void {
    config([
        'omarchy.enabled' => true,
        'omarchy.path' => fixturePath('omarchy/dark/colors.toml'),
    ]);
});

it('resolves the active omarchy theme', function (): void {
    $theme = resolve(ResolveOmarchyTheme::class)->handle();

    expect($theme)->not->toBeNull()
        ->and($theme?->name)->toBe('Warm Glass')
        ->and($theme?->mode)->toBe('dark')
        ->and($theme?->tokens['--background'])->toBe('#0b0b0a')
        ->and($theme?->tokens['--foreground'])->toBe('#e5e3da')
        ->and($theme?->tokens['--card'])->toBe('#1c1c19')
        ->and($theme?->tokens['--popover'])->toBe('#1c1c19')
        ->and($theme?->tokens['--primary'])->toBe('#b6aa8b')
        ->and($theme?->tokens['--primary-foreground'])->toBe('#0b0b0a')
        ->and($theme?->tokens['--primary-hover'])->toBe('color-mix(in oklab, var(--primary) 82%, #ffffff)')
        ->and($theme?->tokens['--primary-soft'])->toBe('#514b3f')
        ->and($theme?->tokens['--muted-foreground'])->toBe('color-mix(in oklab, #e5e3da 70%, #0b0b0a)')
        ->and($theme?->tokens['--destructive'])->toBe('#d99188')
        ->and($theme?->tokens['--destructive-foreground'])->toBe('#edada3')
        ->and($theme?->tokens['--border'])->toBe('#45443d')
        ->and($theme?->tokens['--input'])->toBe('color-mix(in oklab, #e5e3da 32%, #1c1c19)')
        ->and($theme?->tokens['--ring'])->toBe('#b6aa8b')
        ->and($theme?->tokens['--rail'])->toBe('color-mix(in oklab, #e5e3da 28%, #1c1c19)')
        ->and($theme?->tokens['--lane-0'])->toBe('#edada3')
        ->and($theme?->tokens['--lane-5'])->toBe('#c1d0df');
});

it('resolves a light omarchy theme with fallbacks', function (): void {
    config(['omarchy.path' => fixturePath('omarchy/light/colors.toml')]);

    $theme = resolve(ResolveOmarchyTheme::class)->handle();

    expect($theme?->name)->toBe('Solar Light')
        ->and($theme?->mode)->toBe('light')
        ->and($theme?->tokens['--primary-hover'])->toBe('color-mix(in oklab, var(--primary) 88%, #000000)')
        ->and($theme?->tokens['--card'])->toBe('#f6f7f8')
        ->and($theme?->tokens['--muted-foreground'])->toBe('color-mix(in oklab, #121417 70%, #f6f7f8)')
        ->and($theme?->tokens['--destructive'])->toBe('#121417')
        ->and($theme?->tokens['--border'])->toBe('color-mix(in oklab, #121417 22%, #f6f7f8)')
        ->and($theme?->tokens['--lane-3'])->toBe('#121417');
});

it('falls back to a default name and ignores malformed lines', function (): void {
    config(['omarchy.path' => fixturePath('omarchy/anonymous/colors.toml')]);

    $theme = resolve(ResolveOmarchyTheme::class)->handle();

    expect($theme?->name)->toBe('Omarchy')
        ->and($theme?->mode)->toBe('dark')
        ->and($theme?->tokens['--background'])->toBe('#101014')
        ->and($theme?->tokens['--foreground'])->toBe('#f0f0f0');
});

it('mixes the border from the foreground when the theme has no border colour', function (): void {
    config(['omarchy.path' => fixturePath('omarchy/anonymous/colors.toml')]);

    $tokens = resolve(ResolveOmarchyTheme::class)->handle()?->tokens;

    expect($tokens['--border'])->toBe('color-mix(in oklab, #f0f0f0 22%, #101014)')
        ->and($tokens['--input'])->toBe('color-mix(in oklab, #f0f0f0 32%, #101014)')
        ->and($tokens['--rail'])->toBe('color-mix(in oklab, #f0f0f0 28%, #101014)');
});

it('resolves a theme from a glob path', function (): void {
    config(['omarchy.path' => fixturePath('omarchy/dark/colo*rs.toml')]);

    expect(resolve(ResolveOmarchyTheme::class)->handle()?->name)->toBe('Warm Glass');
});

it('returns null when disabled', function (): void {
    config(['omarchy.enabled' => false]);

    expect(resolve(ResolveOmarchyTheme::class)->handle())->toBeNull();
});

it('returns null when no path is configured', function (): void {
    config(['omarchy.path' => '']);

    expect(resolve(ResolveOmarchyTheme::class)->handle())->toBeNull();
});

it('returns null when the theme file is missing', function (): void {
    config(['omarchy.path' => fixturePath('omarchy/missing/colors.toml')]);

    expect(resolve(ResolveOmarchyTheme::class)->handle())->toBeNull();
});

it('returns null when the glob matches nothing', function (): void {
    config(['omarchy.path' => fixturePath('omarchy/*/nope.toml')]);

    expect(resolve(ResolveOmarchyTheme::class)->handle())->toBeNull();
});

it('returns null when the theme has no colors', function (): void {
    config(['omarchy.path' => fixturePath('omarchy/incomplete/colors.toml')]);

    expect(resolve(ResolveOmarchyTheme::class)->handle())->toBeNull();
});

it('resolves the theme once per request', function (): void {
    $action = resolve(ResolveOmarchyTheme::class);
    $theme = $action->handle();

    config(['omarchy.path' => '']);

    expect($theme)->toBeInstanceOf(OmarchyTheme::class)
        ->and($action->handle())->toBe($theme);
});
