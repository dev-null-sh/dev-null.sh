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
