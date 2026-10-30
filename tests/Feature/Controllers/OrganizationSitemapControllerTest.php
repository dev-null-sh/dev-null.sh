<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;

it('lists every organization whose page was made that month', function (): void {
    $laravel = Organization::factory()->create(['handle' => 'laravel', 'created_at' => '2026-10-01 00:00:00']);
    Organization::factory()->create(['handle' => 'symfony', 'created_at' => '2026-10-31 23:59:59']);
    resolve(CreateCommitTopics::class)->handle(Commit::factory()->create(['body' => 'Thanks @laravel']), ['laravel' => $laravel->id]);
    Organization::factory()->create(['handle' => 'last-month', 'created_at' => '2026-09-30 23:59:59']);
    Organization::factory()->create(['handle' => 'next-month', 'created_at' => '2026-11-01 00:00:00']);
    Organization::factory()->create(['handle' => '_renamed-123', 'created_at' => '2026-10-15 00:00:00']);
    Commit::factory()->for(User::factory()->state(['handle' => 'octocat', 'created_at' => '2026-10-15 00:00:00']))->create();

    $response = $this->get(route('organization-sitemap.show', '2026-10'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSeeInOrder([
            '<loc>'.route('user.show', 'laravel').'</loc>',
            '<loc>'.route('user.show', 'symfony').'</loc>',
        ], false)
        ->assertDontSee('last-month')
        ->assertDontSee('next-month')
        ->assertDontSee('_renamed-123')
        ->assertDontSee('octocat');

    expect(mb_substr_count($response->getContent(), '<url>'))->toBe(2);
});

it('dates an organization by the last change of its GitHub profile or the approved commits that mention it', function (string $profile, string $mention, string $lastmod): void {
    $this->travelTo('2026-10-01 00:00:00');
    $laravel = Organization::factory()->create(['handle' => 'laravel']);
    $this->travelTo($profile);
    $laravel->profile->update(['bio' => 'Updated on GitHub.']);
    resolve(CreateCommitTopics::class)->handle(Commit::factory()->create(['body' => '@laravel', 'updated_at' => $mention]), ['laravel' => $laravel->id]);
    resolve(CreateCommitTopics::class)->handle(Commit::factory()->pending()->create(['body' => '@laravel', 'updated_at' => '2026-10-29 00:00:00']), ['laravel' => $laravel->id]);
    resolve(CreateCommitTopics::class)->handle(Commit::factory()->create(['body' => '@symfony', 'updated_at' => '2026-10-28 00:00:00']), ['symfony' => Organization::factory()->create()->id]);

    $this->get(route('organization-sitemap.show', '2026-10'))
        ->assertOk()
        ->assertSee('<loc>'.route('user.show', 'laravel')."</loc>\n        <lastmod>{$lastmod}</lastmod>", false);
})->with([
    'its GitHub profile' => ['2026-10-20 10:00:00', '2026-10-10 10:00:00', '2026-10-20T10:00:00+00:00'],
    'a commit that mentions it' => ['2026-10-10 10:00:00', '2026-10-20 10:00:00', '2026-10-20T10:00:00+00:00'],
]);

it('has no sitemap for a month without organizations', function (): void {
    Organization::factory()->create(['created_at' => '2026-09-15 00:00:00']);
    Organization::factory()->create(['handle' => '_renamed-123', 'created_at' => '2026-10-15 00:00:00']);

    $this->get(route('organization-sitemap.show', '2026-10'))->assertNotFound();
});

it('has no sitemap for a month that does not exist', function (string $month): void {
    $this->get("/sitemaps/organizations-{$month}.xml")->assertNotFound();
})->with(['2026-13', '2026-00', '2026-1', 'abc']);
