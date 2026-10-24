<?php

declare(strict_types=1);

use App\Services\SitemapXml;

it('builds valid empty sitemap XML', function (): void {
    $xml = resolve(SitemapXml::class)->render([]);

    $document = new DOMDocument;

    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->documentElement?->localName)->toBe('urlset')
        ->and($document->getElementsByTagName('url')->length)->toBe(0);
});

it('escapes XML special characters in locations and image URLs', function (): void {
    $url = 'https://example.com/path?one=1&two="quoted"';
    $image = 'https://example.com/photo.png?size=2&mode=wide';

    $xml = resolve(SitemapXml::class)->render([[
        'loc' => $url,
        'lastmod' => '2026-10-16T16:00:00+00:00',
        'images' => [$image],
    ]]);

    $document = new DOMDocument;

    expect($document->loadXML($xml))->toBeTrue()
        ->and($xml)->toContain('one=1&amp;two=')
        ->toContain('size=2&amp;mode=wide')
        ->and($document->getElementsByTagNameNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'loc')->item(0)?->textContent)->toBe($url)
        ->and($document->getElementsByTagNameNS('http://www.google.com/schemas/sitemap-image/1.1', 'loc')->item(0)?->textContent)->toBe($image)
        ->and($document->getElementsByTagName('lastmod')->item(0)?->textContent)->toBe('2026-10-16T16:00:00+00:00');
});

it('includes multiple URLs without adding image nodes to URLs that have none', function (): void {
    $xml = resolve(SitemapXml::class)->render([
        ['loc' => 'https://example.com/a', 'lastmod' => '2026-10-16T10:00:00+00:00'],
        ['loc' => 'https://example.com/b', 'lastmod' => '2026-10-16T11:00:00+00:00', 'images' => ['https://example.com/b.png']],
    ]);

    $document = new DOMDocument;

    expect($document->loadXML($xml))->toBeTrue()
        ->and($document->getElementsByTagName('url')->length)->toBe(2)
        ->and($document->getElementsByTagNameNS('http://www.google.com/schemas/sitemap-image/1.1', 'image')->length)->toBe(1);
});

it('renders the full 10,000-entry sitemap page without losing or duplicating URLs', function (): void {
    $urls = array_map(fn (int $number): array => [
        'loc' => "https://example.com/commits/{$number}",
        'lastmod' => '2026-10-16T11:00:00+00:00',
    ], range(1, 10_000));

    $document = new DOMDocument;
    expect($document->loadXML(resolve(SitemapXml::class)->render($urls)))->toBeTrue()
        ->and($document->getElementsByTagName('url')->length)->toBe(10_000);
});

it('renders the same XML structure as the current Blade template', function (): void {
    $urls = [
        ['loc' => 'https://example.com/a?one=1&two=2', 'lastmod' => '2026-10-16T12:00:00+00:00', 'images' => ['https://example.com/photo.png?one=1&two=2']],
        ['loc' => 'https://example.com/b', 'lastmod' => '2026-10-16T12:00:01+00:00'],
    ];

    $original = new DOMDocument;
    $original->preserveWhiteSpace = false;

    $new = new DOMDocument;
    $new->preserveWhiteSpace = false;

    expect($original->loadXML(view('sitemaps.urls', ['urls' => $urls])->render()))->toBeTrue()
        ->and($new->loadXML(resolve(SitemapXml::class)->render($urls)))->toBeTrue()
        ->and($new->C14N())->toBe($original->C14N());
});
