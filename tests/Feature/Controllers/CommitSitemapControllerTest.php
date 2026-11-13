<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitImage;

it('lists approved commits only', function (): void {
    $commit = Commit::factory()->create();
    $pending = Commit::factory()->pending()->create();
    $rejected = Commit::factory()->rejected()->create();
    $deleted = Commit::factory()->create();
    $deleted->delete();

    $response = $this->get(route('commit-sitemap.show', now()->toDateString()));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('commit.show', [$commit->user, $commit]).'</loc>', false)
        ->assertSee('<lastmod>'.$commit->updated_at->toAtomString().'</lastmod>', false)
        ->assertDontSee($pending->id)
        ->assertDontSee($rejected->id)
        ->assertDontSee($deleted->id);
})->freezeTime();

it('lists the images of approved commits', function (): void {
    $image = CommitImage::factory()->create();

    $this->get(route('commit-sitemap.show', now()->toDateString()))
        ->assertOk()
        ->assertSee('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', false)
        ->assertSee('<image:loc>'.$image->url().'</image:loc>', false);
})->freezeTime();

it('lists every commit once, with its images in order', function (): void {
    $commit = Commit::factory()->create();
    $second = CommitImage::factory()->for($commit)->create(['position' => 1]);
    $first = CommitImage::factory()->for($commit)->create(['position' => 0]);
    $other = Commit::factory()->create();

    $response = $this->get(route('commit-sitemap.show', now()->toDateString()));

    $response->assertOk()
        ->assertSeeInOrder([
            '<loc>'.route('commit.show', [$commit->user, $commit]).'</loc>',
            '<image:loc>'.$first->url().'</image:loc>',
            '<image:loc>'.$second->url().'</image:loc>',
        ], false);

    expect(mb_substr_count($response->getContent(), '<url>'))->toBe(2)
        ->and(mb_substr_count($response->getContent(), route('commit.show', [$other->user, $other])))->toBe(1);
})->freezeTime();

it('lists the commits written that day', function (): void {
    $commit = Commit::factory()->create(['created_at' => '2026-10-29 00:00:00']);
    $evening = Commit::factory()->create(['created_at' => '2026-10-29 23:59:59']);
    $dayBefore = Commit::factory()->create(['created_at' => '2026-10-28 23:59:59']);
    $dayAfter = Commit::factory()->create(['created_at' => '2026-10-30 00:00:00']);

    $this->get(route('commit-sitemap.show', '2026-10-29'))
        ->assertOk()
        ->assertSee($commit->id)
        ->assertSee($evening->id)
        ->assertDontSee($dayBefore->id)
        ->assertDontSee($dayAfter->id);
});

it('has no sitemap for a day that does not exist', function (string $day): void {
    $this->get("/sitemaps/commits-{$day}.xml")->assertNotFound();
})->with(['2026-02-30', '2026-13-01', '2026-10-00', '2026-10-1', '1', 'abc']);

it('has no sitemap for a day without approved commits', function (): void {
    Commit::factory()->pending()->create(['created_at' => '2026-10-29 08:00:00']);
    Commit::factory()->create(['created_at' => '2026-10-29 09:00:00'])->delete();

    $this->get(route('commit-sitemap.show', '2026-10-29'))->assertNotFound();
});
