<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('lists the profiles with approved commits', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);
    Commit::factory()->for($user)->create();
    Commit::factory()->for(User::factory()->state(['handle' => 'pending_only']))->pending()->create();
    User::factory()->create(['handle' => 'no_commits']);

    $response = $this->get(route('user-sitemap.show', 1));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('user.show', $user).'</loc>', false)
        ->assertSee('<lastmod>'.$user->updated_at->toAtomString().'</lastmod>', false)
        ->assertDontSee('pending_only')
        ->assertDontSee('no_commits');
});

it('lists nothing past the last page', function (): void {
    Commit::factory()->create();

    $this->get(route('user-sitemap.show', 2))
        ->assertOk()
        ->assertDontSee('<url>', false);
});
