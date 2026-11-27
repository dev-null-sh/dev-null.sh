<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;

it('lists the profiles with approved commits that signed up that month', function (): void {
    $user = User::factory()->create(['handle' => 'octocat', 'created_at' => '2026-10-01 00:00:00']);
    Commit::factory()->for($user)->create();
    Commit::factory()->for(User::factory()->state(['handle' => 'last_month', 'created_at' => '2026-09-30 23:59:59']))->create();
    Commit::factory()->for(User::factory()->state(['handle' => 'next_month', 'created_at' => '2026-11-01 00:00:00']))->create();
    Commit::factory()->for(User::factory()->state(['handle' => 'pending_only', 'created_at' => '2026-10-15 00:00:00']))->pending()->create();
    User::factory()->create(['handle' => 'no_commits', 'created_at' => '2026-10-15 00:00:00']);

    $response = $this->get(route('user-sitemap.show', '2026-10'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('user.show', $user).'</loc>', false)
        ->assertSee('<lastmod>'.$user->updated_at->toAtomString().'</lastmod>', false)
        ->assertDontSee('last_month')
        ->assertDontSee('next_month')
        ->assertDontSee('pending_only')
        ->assertDontSee('no_commits');
});

it('dates a profile by the last change of its account, its GitHub profile or its approved commits', function (string $account, string $profile, string $commit, string $lastmod): void {
    $this->travelTo('2026-10-01 00:00:00');
    $user = User::factory()->create(['updated_at' => $account]);
    Profile::query()->where('github_id', $user->github_id)->toBase()->update(['updated_at' => $profile]);
    Commit::factory()->for($user)->create(['updated_at' => $commit]);
    Commit::factory()->for($user)->pending()->create(['updated_at' => '2026-10-29 00:00:00']);

    $this->get(route('user-sitemap.show', '2026-10'))
        ->assertOk()
        ->assertSee("<lastmod>{$lastmod}</lastmod>", false);
})->with([
    'the account' => ['2026-10-20 10:00:00', '2026-10-10 10:00:00', '2026-10-05 10:00:00', '2026-10-20T10:00:00+00:00'],
    'the GitHub profile' => ['2026-10-05 10:00:00', '2026-10-20 10:00:00', '2026-10-10 10:00:00', '2026-10-20T10:00:00+00:00'],
    'an approved commit' => ['2026-10-05 10:00:00', '2026-10-10 10:00:00', '2026-10-20 10:00:00', '2026-10-20T10:00:00+00:00'],
]);

it('has no sitemap for a month that does not exist', function (string $month): void {
    $this->get("/sitemaps/users-{$month}.xml")->assertNotFound();
})->with(['2026-13', '2026-00', '2026-1', '20261-01', 'abc']);

it('has no sitemap for a month without profiles to list', function (): void {
    Commit::factory()->for(User::factory()->state(['created_at' => '2026-10-01 00:00:00']))->pending()->create();
    User::factory()->create(['created_at' => '2026-10-02 00:00:00']);

    $this->get(route('user-sitemap.show', '2026-10'))->assertNotFound();
});
