<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('lists a sitemap for every month of sign-ups and every day of commits', function (): void {
    $user = User::factory()->create(['created_at' => '2026-09-30 23:59:59']);
    Commit::factory()->for($user)->create(['created_at' => '2026-10-15 08:00:00']);
    Commit::factory()->for($user)->create(['created_at' => '2026-10-15 23:59:59']);
    Commit::factory()->create(['created_at' => '2026-10-29 00:00:00']);

    $response = $this->get(route('sitemap.index'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
        ->assertSeeInOrder([
            '<loc>'.route('user-sitemap.show', '2026-09').'</loc>',
            '<loc>'.route('user-sitemap.show', now()->format('Y-m')).'</loc>',
            '<loc>'.route('commit-sitemap.show', '2026-10-15').'</loc>',
            '<loc>'.route('commit-sitemap.show', '2026-10-29').'</loc>',
        ], false);

    expect(mb_substr_count($response->getContent(), '<sitemap>'))->toBe(4);
})->freezeTime();

it('leaves out the days and months with nothing approved', function (): void {
    Commit::factory()->pending()->create(['created_at' => '2026-10-15 08:00:00']);
    Commit::factory()->for(User::factory()->state(['created_at' => '2026-09-01 00:00:00']))->rejected()->create();

    $response = $this->get(route('sitemap.index'));

    $response->assertOk()
        ->assertDontSee('2026-10-15')
        ->assertDontSee('2026-09');
});

it('serves every sitemap without cookies, so a cache can share it', function (string $route): void {
    Commit::factory()->create();

    $response = $this->get(route($route, match ($route) {
        'sitemap.index' => [],
        'user-sitemap.show' => now()->format('Y-m'),
        'commit-sitemap.show' => now()->toDateString(),
    }));

    $response->assertOk()
        ->assertHeader('Cache-Control', 'max-age=3600, public')
        ->assertHeaderMissing('Set-Cookie');
})->with(['sitemap.index', 'user-sitemap.show', 'commit-sitemap.show'])->freezeTime();
