<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

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

it('tells phones to open the installed app without browser chrome', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">', false)
        ->assertSee('<meta name="application-name" content="/dev/null">', false)
        ->assertSee('<meta name="mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
        ->assertSee('<meta name="apple-mobile-web-app-title" content="/dev/null">', false)
        ->assertSee('<meta name="apple-mobile-web-app-status-bar-style" content="default">', false);
});

it('describes the app as an installable standalone app', function (): void {
    $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->id->toBe('/')
        ->name->toBe('/dev/null')
        ->short_name->toBe('/dev/null')
        ->lang->toBe('en')
        ->start_url->toBe('/')
        ->scope->toBe('/')
        ->display->toBe('standalone')
        ->background_color->toBe('#111112')
        ->theme_color->toBe('#111112')
        ->prefer_related_applications->toBeFalse()
        ->launch_handler->toBe(['client_mode' => 'navigate-existing'])
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

it('offers shortcuts to pages that exist', function (): void {
    $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect(collect($manifest['shortcuts'])->pluck('url')->all())
        ->toBe(['/log/following', '/log/latest', '/notifications']);

    foreach ($manifest['shortcuts'] as $shortcut) {
        expect(Route::getRoutes()->match(request()->create($shortcut['url']))->getName())
            ->toBeIn(['log.show', 'notification.index']);

        foreach ($shortcut['icons'] as $icon) {
            expect(public_path(mb_ltrim((string) $icon['src'], '/')))->toBeFile();
        }
    }
});

it('ships a service worker that falls back to the offline page', function (): void {
    $worker = file_get_contents(public_path('sw.js'));
    $offline = file_get_contents(public_path('offline.html'));

    expect($worker)
        ->toContain("const offlineUrl = '/offline.html';")
        ->toContain("request.mode === 'navigate'")
        ->toContain("url.pathname.startsWith('/build/')")
        ->and($offline)
        ->toContain('<meta name="robots" content="noindex">')
        ->toContain("You're offline.")
        ->toContain("addEventListener('online'");
});

it('registers the service worker with the current asset version', function (): void {
    expect(file_get_contents(resource_path('js/app.tsx')))
        ->toContain('registerServiceWorker(props.initialPage.version)')
        ->and(file_get_contents(resource_path('js/lib/service-worker.ts')))
        ->toContain("new URL('/sw.js', window.location.origin)")
        ->toContain("updateViaCache: 'none'");
});
