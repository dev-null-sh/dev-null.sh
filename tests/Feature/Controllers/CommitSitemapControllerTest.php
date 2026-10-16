<?php

declare(strict_types=1);

use App\Models\Commit;

it('lists approved commits only', function (): void {
    $commit = Commit::factory()->create();
    $pending = Commit::factory()->pending()->create();
    $rejected = Commit::factory()->rejected()->create();
    $deleted = Commit::factory()->create();
    $deleted->delete();

    $response = $this->get(route('commit-sitemap.show', 1));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('commit.show', [$commit->user, $commit]).'</loc>', false)
        ->assertSee('<lastmod>'.$commit->updated_at->toAtomString().'</lastmod>', false)
        ->assertDontSee($pending->id)
        ->assertDontSee($rejected->id)
        ->assertDontSee($deleted->id);
});

it('does not route pages that are not positive numbers', function (string $page): void {
    $this->get("/sitemaps/commits-{$page}.xml")->assertNotFound();
})->with(['0', 'abc', '-1']);
