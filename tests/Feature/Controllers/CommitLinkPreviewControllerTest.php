<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;

it('returns the link preview of a commit once it is fetched', function (): void {
    $linkPreview = LinkPreview::factory()->create(['url' => 'https://laravel.com']);
    $commit = Commit::factory()->for($linkPreview)->create();
    $url = route('commit-link-preview.show', $commit);

    $this->getJson($url)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('link_preview.url', 'https://laravel.com')
        ->assertJsonPath('link_preview.title', null)
        ->assertJsonPath('link_preview.is_pending', true);

    $linkPreview->update(['title' => 'Laravel', 'site_name' => 'Laravel', 'fetched_at' => now()]);

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('link_preview.title', 'Laravel')
        ->assertJsonPath('link_preview.site_name', 'Laravel')
        ->assertJsonPath('link_preview.is_pending', false);
});

it('returns no link preview for a commit without one', function (): void {
    $commit = Commit::factory()->create();

    $this->getJson(route('commit-link-preview.show', $commit))
        ->assertOk()
        ->assertExactJson(['link_preview' => null]);
});

it('returns the link preview of the authors own pending commit', function (): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->for(LinkPreview::factory())->pending()->create();

    $this->actingAs($author)
        ->getJson(route('commit-link-preview.show', $commit))
        ->assertOk()
        ->assertJsonPath('link_preview.is_pending', true);
});

it('hides the link preview of commits the viewer cannot see', function (?string $viewer): void {
    $commit = Commit::factory()->for(LinkPreview::factory()->fetched())->pending()->create();

    if ($viewer === 'user') {
        $this->actingAs(User::factory()->create());
    }

    $this->getJson(route('commit-link-preview.show', $commit))->assertNotFound();
})->with(['guest' => null, 'user' => 'user']);
