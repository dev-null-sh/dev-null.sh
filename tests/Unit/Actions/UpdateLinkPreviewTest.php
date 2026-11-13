<?php

declare(strict_types=1);

use App\Actions\UpdateLinkPreview;
use App\Contracts\Services\LinkPreviewProvider;
use App\Models\LinkPreview;
use Illuminate\Http\Client\ConnectionException;

it('stores the metadata of the page and marks the link as fetched', function (): void {
    $this->mock(LinkPreviewProvider::class)->shouldReceive('metadata')->once()->with('https://example.com/post')->andReturn([
        'title' => 'Shipping link previews',
        'description' => 'How devnull unfurls links.',
        'image_url' => 'https://example.com/og.png',
        'site_name' => 'Example Blog',
    ]);
    $linkPreview = LinkPreview::factory()->create(['url' => 'https://example.com/post']);

    resolve(UpdateLinkPreview::class)->handle($linkPreview);

    expect($linkPreview->refresh())
        ->url->toBe('https://example.com/post')
        ->title->toBe('Shipping link previews')
        ->description->toBe('How devnull unfurls links.')
        ->image_url->toBe('https://example.com/og.png')
        ->site_name->toBe('Example Blog')
        ->fetched_at->not->toBeNull();
});

it('throws without marking the link as fetched when the page may be reachable later', function (): void {
    $this->mock(LinkPreviewProvider::class)->shouldReceive('metadata')->andThrow(new ConnectionException('unreachable'));
    $linkPreview = LinkPreview::factory()->create(['url' => 'https://example.com/']);

    expect(fn () => resolve(UpdateLinkPreview::class)->handle($linkPreview))->toThrow(ConnectionException::class)
        ->and($linkPreview->refresh()->fetched_at)->toBeNull();
});
