<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('returns the link card of a commit while it is pending and once it is fetched', function (): void {
    $linkPreview = LinkPreview::factory()->create(['url' => 'https://laravel.com']);
    $commit = Commit::factory()->for($linkPreview)->create();
    $url = route('api-commit-link-preview.show', $commit);

    signedApiRequest($this->keyPair, 'GET', $url)
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('link_preview.url', 'https://laravel.com')
        ->assertJsonPath('link_preview.title', null)
        ->assertJsonPath('link_preview.is_pending', true);

    $linkPreview->update(['title' => 'Laravel', 'site_name' => 'Laravel', 'fetched_at' => now()]);

    signedApiRequest($this->keyPair, 'GET', $url)
        ->assertOk()
        ->assertJsonPath('link_preview.title', 'Laravel')
        ->assertJsonPath('link_preview.is_pending', false);
});

it('returns no link card for a commit without one', function (): void {
    signedApiRequest($this->keyPair, 'GET', route('api-commit-link-preview.show', Commit::factory()->create()))
        ->assertOk()
        ->assertExactJson(['link_preview' => null]);
});

it('returns the link card of the users own pending commit', function (): void {
    $commit = Commit::factory()->for($this->user)->for(LinkPreview::factory())->pending()->create();

    signedApiRequest($this->keyPair, 'GET', route('api-commit-link-preview.show', $commit))
        ->assertOk()
        ->assertJsonPath('link_preview.is_pending', true);
});

it('returns not found for a commit the user may not see', function (bool $signed): void {
    $commit = Commit::factory()->for(LinkPreview::factory()->fetched())->pending()->create();
    $url = route('api-commit-link-preview.show', $commit);

    ($signed ? signedApiRequest($this->keyPair, 'GET', $url) : $this->getJson($url))->assertNotFound();
})->with(['signed' => true, 'guest' => false]);

it('returns the link card of a public commit to guests, as the web does', function (): void {
    $commit = Commit::factory()->for(LinkPreview::factory()->fetched()->create(['url' => 'https://laravel.com']))->create();

    $this->getJson(route('api-commit-link-preview.show', $commit))
        ->assertOk()
        ->assertJsonPath('link_preview.url', 'https://laravel.com');
});
