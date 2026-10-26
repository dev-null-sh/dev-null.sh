<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->withoutVite();
    $this->app->usePublicPath(storage_path('framework/testing/public'));
    File::ensureDirectoryExists(public_path('downloads'));
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('framework/testing/public'));
});

function publishMacApp(array $overrides = []): void
{
    File::put(public_path('downloads/DevNull.dmg'), 'dmg');
    File::put(public_path('downloads/DevNull.json'), json_encode([
        'version' => '1.0.0',
        'build' => 1135,
        'revision' => 'a003e08f',
        'released_at' => '2026-10-25T01:32:14Z',
        'minimum_system_version' => '14.0',
        'notarized' => false,
        'size' => 3915629,
        'sha256' => str_repeat('ab', 32),
        ...$overrides,
    ]));
}

it('offers the published Mac app', function (): void {
    publishMacApp();

    $this->get(route('app.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/index')
            ->where('release', [
                'version' => '1.0.0',
                'build' => 1135,
                'revision' => 'a003e08f',
                'released_at' => '2026-10-25T01:32:14Z',
                'minimum_system_version' => '14.0',
                'notarized' => false,
                'size' => 3915629,
                'sha256' => str_repeat('ab', 32),
                'download_url' => url('downloads/DevNull.dmg'),
                'url' => route('app.index'),
            ]));
});

it('renders for signed in users too', function (): void {
    publishMacApp(['notarized' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('app.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/index')->where('release.notarized', true));
});

it('has no release until both the DMG and its release file are published', function (?string $file): void {
    publishMacApp();

    if ($file !== null) {
        File::delete(public_path('downloads/'.$file));
    } else {
        File::put(public_path('downloads/DevNull.json'), 'not json');
    }

    $this->get(route('app.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('app/index')->where('release', null));
})->with([
    'without the DMG' => 'DevNull.dmg',
    'without the release file' => 'DevNull.json',
    'with an unreadable release file' => null,
]);

it('tells Macs from everything else by their user agent', function (string $agent, bool $isMac): void {
    $this->withHeader('User-Agent', $agent)
        ->get(route('app.index'))
        ->assertInertia(fn ($page) => $page->where('isMac', $isMac));
})->with([
    'Safari on a Mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15', true],
    'Chrome on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36', false],
    'Safari on an iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 26_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1', false],
]);

it('describes the page and the Mac app to search engines and link previews', function (): void {
    publishMacApp();

    $this->get(route('app.index'))
        ->assertOk()
        ->assertSee('<title>Apps - /dev/null</title>', false)
        ->assertSee('<link rel="canonical" href="'.route('app.index').'">', false)
        ->assertSee(url('/images/apps/og.png'), false)
        ->assertSee('"@type":"SoftwareApplication"', false)
        ->assertSee('"softwareVersion":"1.0.0"', false)
        ->assertSee('"operatingSystem":"macOS 14.0 or later"', false);
});

it('only describes the Mac app as software once it is published', function (): void {
    $this->get(route('app.index'))
        ->assertOk()
        ->assertSee('<title>Apps - /dev/null</title>', false)
        ->assertDontSee('SoftwareApplication', false);
});

it('sends the old Mac app page to the apps', function (): void {
    $this->get('/mac')->assertRedirect(route('app.index'))->assertStatus(301);
});
