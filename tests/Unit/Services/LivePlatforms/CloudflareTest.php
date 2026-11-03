<?php

declare(strict_types=1);

use App\Services\LivePlatforms\Cloudflare;

it('never accepts a pasted link', function (): void {
    expect(resolve(Cloudflare::class)->accepts('https://customer-abc.cloudflarestream.com/input-1/iframe'))->toBeFalse();
});

it('plays the stream where it is shown', function (): void {
    expect(resolve(Cloudflare::class)->stream('https://customer-abc.cloudflarestream.com/input-1/iframe')->toArray())->toBe([
        'url' => 'https://customer-abc.cloudflarestream.com/input-1/iframe',
        'thumbnail_url' => 'https://customer-abc.cloudflarestream.com/input-1/thumbnails/thumbnail.jpg',
        'embed_url' => 'https://customer-abc.cloudflarestream.com/input-1/iframe',
    ]);
});
