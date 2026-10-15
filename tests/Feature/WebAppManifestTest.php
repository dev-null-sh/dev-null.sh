<?php

declare(strict_types=1);

it('links the favicons and the web app manifest', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', false)
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false)
        ->assertSee('<link rel="manifest" href="/site.webmanifest">', false);

    foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png'] as $icon) {
        expect(public_path($icon))->toBeFile();
    }
});

it('describes the app as an installable standalone app', function (): void {
    $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->name->toBe('/dev/null')
        ->short_name->toBe('/dev/null')
        ->start_url->toBe('/')
        ->display->toBe('standalone')
        ->background_color->toBe('#f6f7f8')
        ->theme_color->toBe('#f6f7f8')
        ->and(collect($manifest['icons'])->map(fn (array $icon): string => "{$icon['sizes']} {$icon['purpose']}")->all())
        ->toBe(['192x192 any', '512x512 any', '512x512 maskable']);

    foreach ($manifest['icons'] as $icon) {
        $path = public_path(mb_ltrim((string) $icon['src'], '/'));

        expect($path)->toBeFile()
            ->and(getimagesize($path))->toMatchArray([
                0 => (int) $icon['sizes'],
                1 => (int) $icon['sizes'],
                'mime' => $icon['type'],
            ]);
    }
});
