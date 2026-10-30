<?php

declare(strict_types=1);

use App\Contracts\Services\LinkPreviewProvider;
use App\Services\LinkPreviewProviders\NullLinkPreviewProvider;
use Illuminate\Support\Facades\Http;

it('is the link preview provider in testing', function (): void {
    expect(resolve(LinkPreviewProvider::class))->toBeInstanceOf(NullLinkPreviewProvider::class);
});

it('fetches no page', function (): void {
    Http::fake();

    expect(new NullLinkPreviewProvider()->metadata('https://example.com/'))
        ->toBe(['title' => null, 'description' => null, 'image_url' => null, 'site_name' => null]);

    Http::assertNothingSent();
});
