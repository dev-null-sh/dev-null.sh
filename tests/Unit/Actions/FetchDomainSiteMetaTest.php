<?php

declare(strict_types=1);

use App\Actions\FetchDomainSiteMeta;
use App\Contracts\Services\DnsResolver;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }

        public function txt(string $host): ?array
        {
            return [];
        }
    });
});

it('reads description and https og image from the homepage', function (): void {
    Http::fake([
        'https://example.com/*' => Http::response(<<<'HTML'
            <html>
                <meta property="og:description" content="  Ship &amp; scale  ">
                <meta property="og:image" content="/images/og.png">
            </html>
            HTML, 200, ['Content-Type' => 'text/html']),
    ]);

    expect(resolve(FetchDomainSiteMeta::class)->forDomain('example.com'))
        ->toBe([
            'description' => 'Ship & scale',
            'image_url' => 'https://example.com/images/og.png',
        ]);
});

it('prefers og:image:secure_url and ignores non-https images', function (): void {
    Http::fake([
        'https://example.com/*' => Http::response(<<<'HTML'
            <html>
                <meta property="og:image" content="http://example.com/insecure.png">
                <meta property="og:image:secure_url" content="https://cdn.example.com/secure.png">
            </html>
            HTML, 200, ['Content-Type' => 'text/html']),
    ]);

    expect(resolve(FetchDomainSiteMeta::class)->forDomain('example.com')['image_url'])
        ->toBe('https://cdn.example.com/secure.png');
});

it('returns nulls when the homepage cannot be fetched', function (): void {
    Http::fake(['https://example.com/*' => Http::response(status: 503)]);

    expect(resolve(FetchDomainSiteMeta::class)->forDomain('example.com'))
        ->toBe([
            'description' => null,
            'image_url' => null,
        ]);
});

it('returns nulls when the domain resolves to a private address', function (): void {
    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['10.0.0.5'];
        }

        public function txt(string $host): ?array
        {
            return [];
        }
    });

    Http::fake();

    expect(resolve(FetchDomainSiteMeta::class)->forDomain('example.com'))
        ->toBe([
            'description' => null,
            'image_url' => null,
        ]);

    Http::assertNothingSent();
});
