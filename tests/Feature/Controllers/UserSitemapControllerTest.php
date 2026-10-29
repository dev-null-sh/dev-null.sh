<?php

declare(strict_types=1);

use App\Models\Commit;
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

it('has no sitemap for a month that does not exist', function (string $month): void {
    $this->get("/sitemaps/users-{$month}.xml")->assertNotFound();
})->with(['2026-13', '2026-00', '2026-1', '20261-01', 'abc']);
