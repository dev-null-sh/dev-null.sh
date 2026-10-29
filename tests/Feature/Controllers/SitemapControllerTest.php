<?php

declare(strict_types=1);

use App\Models\Commit;

it('lists a sitemap for every page of profiles and commits', function (): void {
    Commit::factory()->create();

    $response = $this->get(route('sitemap.index'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
        ->assertSee('<loc>'.route('user-sitemap.show', 1).'</loc>', false)
        ->assertSee('<loc>'.route('commit-sitemap.show', 1).'</loc>', false)
        ->assertDontSee(route('commit-sitemap.show', 2));
});

it('serves every sitemap without cookies, so a cache can share it', function (string $route): void {
    Commit::factory()->create();

    $response = $this->get(route($route, $route === 'sitemap.index' ? [] : 1));

    $response->assertOk()
        ->assertHeader('Cache-Control', 'max-age=3600, public')
        ->assertHeaderMissing('Set-Cookie');
})->with(['sitemap.index', 'user-sitemap.show', 'commit-sitemap.show']);
