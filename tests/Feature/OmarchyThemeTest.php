<?php

declare(strict_types=1);

it('applies omarchy tokens and the jetbrains font for the omarchy appearance', function (): void {
    config([
        'omarchy.enabled' => true,
        'omarchy.path' => fixturePath('omarchy/dark/colors.toml'),
    ]);

    $this->withUnencryptedCookie('appearance', 'omarchy')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-omarchy-mode="dark"', false)
        ->assertSee('--background: #0b0b0a;', false)
        ->assertSee('--primary: #b6aa8b;', false)
        ->assertSee('fonts.bunny.net/css?family=jetbrains-mono', false)
        ->assertSee('--font-sans: "JetBrains Mono"', false)
        ->assertSee('--font-mono: "JetBrains Mono"', false);
});

it('does not apply omarchy tokens for other appearances', function (): void {
    config([
        'omarchy.enabled' => true,
        'omarchy.path' => fixturePath('omarchy/dark/colors.toml'),
    ]);

    $this->withUnencryptedCookie('appearance', 'dark')
        ->get(route('home'))
        ->assertOk()
        ->assertDontSee('--background: #0b0b0a;', false)
        ->assertDontSee('fonts.bunny.net', false);
});

it('falls back to system when omarchy is requested but unavailable', function (): void {
    config(['omarchy.enabled' => false]);

    $this->withUnencryptedCookie('appearance', 'omarchy')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-omarchy-mode=""', false)
        ->assertSee('name="theme-color" content="#f6f7f8"', false)
        ->assertDontSee('--primary-soft:', false);
});
