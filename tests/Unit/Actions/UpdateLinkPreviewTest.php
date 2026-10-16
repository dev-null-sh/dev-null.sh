<?php

declare(strict_types=1);

use App\Actions\UpdateLinkPreview;
use App\Contracts\Services\DnsResolver;
use App\Models\LinkPreview;
use App\Services\DnsResolvers\SystemDnsResolver;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        private const array HOSTS = [
            'example.com' => ['93.184.216.34'],
            'www.example.com' => ['93.184.216.35'],
            'v6.example.com' => ['2606:2800:220:1:248:1893:25c8:1946'],
            'internal.example.com' => ['10.0.0.5'],
            'mixed.example.com' => ['93.184.216.34', '10.0.0.5'],
        ];

        public function resolve(string $host): array
        {
            return self::HOSTS[$host] ?? new SystemDnsResolver(fn (): array => [])->resolve($host);
        }
    });
});

function unfurl(string $url): LinkPreview
{
    $linkPreview = LinkPreview::factory()->create(['url' => $url]);

    resolve(UpdateLinkPreview::class)->handle($linkPreview);

    return $linkPreview->refresh();
}

function htmlResponse(string $html, int $status = 200): PromiseInterface
{
    return Http::response($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
}

function expectNoMetadata(LinkPreview $linkPreview): void
{
    expect($linkPreview->only(['title', 'description', 'image_url', 'site_name']))
        ->toBe(['title' => null, 'description' => null, 'image_url' => null, 'site_name' => null])
        ->and($linkPreview->fetched_at)->not->toBeNull();
}

it('stores the open graph metadata of a page', function (): void {
    Http::fake(['https://example.com/post' => htmlResponse(<<<'HTML'
        <!doctype html>
        <html>
        <head>
            <title>Ignored title</title>
            <meta name="description" content="Ignored description">
            <meta property="og:title" content="Shipping link previews">
            <meta property="og:description" content="How devnull unfurls links.">
            <meta property="og:image" content="https://example.com/og.png">
            <meta property="og:site_name" content="Example Blog">
            <meta name="twitter:title" content="Ignored twitter title">
        </head>
        <body><p>Hello</p></body>
        </html>
        HTML)]);

    $linkPreview = unfurl('https://example.com/post');

    expect($linkPreview)
        ->url->toBe('https://example.com/post')
        ->title->toBe('Shipping link previews')
        ->description->toBe('How devnull unfurls links.')
        ->image_url->toBe('https://example.com/og.png')
        ->site_name->toBe('Example Blog')
        ->fetched_at->not->toBeNull();
});

it('sends a pinned and bounded request that identifies itself', function (): void {
    config(['app.url' => 'https://devnull.com']);

    $options = [];

    Http::fake(function (Request $request, array $sent) use (&$options): PromiseInterface {
        $options = $sent;

        return htmlResponse('<title>Hello</title>');
    });

    unfurl('https://example.com/post');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.com/post'
        && $request->method() === 'GET'
        && $request->header('User-Agent') === ['Mozilla/5.0 (compatible; devnull-linkpreview/1.0; +https://devnull.com)']
        && $request->header('Accept') === ['text/html']);

    expect($options)
        ->allow_redirects->toBeFalse()
        ->connect_timeout->toBe(3)
        ->timeout->toBe(5)
        ->decode_content->toBeFalse()
        ->and($options['curl'][CURLOPT_CONNECT_TO])->toBe(['::93.184.216.34:443'])
        ->and($options['progress'](0, 1_048_576, 0, 0))->toBeFalse()
        ->and($options['progress'](2_000_000, 1_048_577, 0, 0))->toBeTrue();
});

it('pins the connection to the port and address that were checked', function (string $url, string $connectTo): void {
    $options = [];

    Http::fake(function (Request $request, array $sent) use (&$options): PromiseInterface {
        $options = $sent;

        return htmlResponse('<title>Hello</title>');
    });

    expect(unfurl($url)->title)->toBe('Hello')
        ->and($options['curl'][CURLOPT_CONNECT_TO])->toBe([$connectTo]);
})->with([
    'http' => ['http://example.com/', '::93.184.216.34:80'],
    'explicit default https port' => ['https://example.com:443/', '::93.184.216.34:443'],
    'explicit http port' => ['http://example.com:80/', '::93.184.216.34:80'],
    'ipv6' => ['https://v6.example.com/', '::[2606:2800:220:1:248:1893:25c8:1946]:443'],
    'public ip literal' => ['https://93.184.216.34/', '::93.184.216.34:443'],
]);

it('prefers the secure open graph image', function (): void {
    Http::fake(['https://example.com/*' => htmlResponse(<<<'HTML'
        <meta property="og:title" content="Secure">
        <meta property="og:image" content="http://example.com/og.png">
        <meta property="og:image:secure_url" content="https://secure.example.com/og.png">
        HTML)]);

    expect(unfurl('https://example.com/')->image_url)->toBe('https://secure.example.com/og.png');
});

it('falls back to twitter tags', function (): void {
    Http::fake(['https://example.com/*' => htmlResponse(<<<'HTML'
        <title>Ignored title</title>
        <meta name="description" content="Ignored description">
        <meta name="twitter:title" content="Twitter title">
        <meta name="twitter:description" content="Twitter description">
        <meta name="twitter:image" content="https://example.com/twitter.png">
        HTML)]);

    expect(unfurl('https://example.com/'))
        ->title->toBe('Twitter title')
        ->description->toBe('Twitter description')
        ->image_url->toBe('https://example.com/twitter.png')
        ->site_name->toBeNull();
});

it('falls back to the title element and meta description', function (): void {
    Http::fake(['https://example.com/*' => htmlResponse(<<<'HTML'
        <head>
            <title>Plain title</title>
            <meta name="description" content="Plain description">
            <meta property="og:title" content="   ">
        </head>
        HTML)]);

    expect(unfurl('https://example.com/'))
        ->title->toBe('Plain title')
        ->description->toBe('Plain description')
        ->image_url->toBeNull();
});

it('leaves the metadata empty when a page has none', function (): void {
    Http::fake(['https://example.com/*' => htmlResponse('<p>Nothing to see here</p>')]);

    expectNoMetadata(unfurl('https://example.com/'));
});

it('resolves relative image urls against the page', function (string $image, string $resolved): void {
    Http::fake(['https://example.com/*' => htmlResponse("<meta property=\"og:image\" content=\"{$image}\">")]);

    expect(unfurl('https://example.com/blog/post')->image_url)->toBe($resolved);
})->with([
    'root relative' => ['/images/og.png', 'https://example.com/images/og.png'],
    'path relative' => ['og.png', 'https://example.com/blog/og.png'],
    'parent directory' => ['../og.png', 'https://example.com/og.png'],
    'protocol relative' => ['//cdn.example.net/og.png', 'https://cdn.example.net/og.png'],
]);

it('only keeps images served over https', function (string $image): void {
    Http::fake(['https://example.com/*' => htmlResponse("<meta property=\"og:image\" content=\"{$image}\">")]);

    expect(unfurl('https://example.com/')->image_url)->toBeNull();
})->with([
    'http' => 'http://example.com/og.png',
    'data' => 'data:image/png;base64,iVBORw0KGgo=',
    'javascript' => 'javascript:alert(1)',
    'blank' => '   ',
    'too long' => 'https://example.com/'.str_repeat('a', 2048),
]);

it('uses the next image when the first one is not served over https', function (): void {
    Http::fake(['https://example.com/*' => htmlResponse(<<<'HTML'
        <meta property="og:image" content="http://example.com/og.png">
        <meta name="twitter:image" content="https://example.com/twitter.png">
        HTML)]);

    expect(unfurl('https://example.com/')->image_url)->toBe('https://example.com/twitter.png');
});

it('reads html pages', function (string $contentType): void {
    Http::fake(['https://example.com/*' => Http::response('<title>A page</title>', 200, ['Content-Type' => $contentType])]);

    expect(unfurl('https://example.com/')->title)->toBe('A page');
})->with([
    'text/html',
    'TEXT/HTML; charset=UTF-8',
    'application/xhtml+xml',
]);

it('ignores responses that are not html', function (array $headers): void {
    Http::fake(['https://example.com/*' => Http::response('<title>Not a page</title>', 200, $headers)]);

    expectNoMetadata(unfurl('https://example.com/'));
})->with([
    'json' => [['Content-Type' => 'application/json']],
    'image' => [['Content-Type' => 'image/png']],
    'plain text' => [['Content-Type' => 'text/plain']],
    'no content type' => [[]],
]);

it('ignores client error responses', function (int $status): void {
    Http::fake(['https://example.com/*' => htmlResponse('<title>Something went wrong</title>', $status)]);

    expectNoMetadata(unfurl('https://example.com/'));
})->with([400, 401, 403, 404, 410, 451]);

it('cleans up whitespace and entities', function (): void {
    Http::fake(['https://example.com/*' => htmlResponse(<<<'HTML'
        <meta property="og:title" content="  Tom &amp;amp; Jerry&#039;s
              new   show  ">
        <meta property="og:description" content="Line one&#10;&#10;line&nbsp; two">
        <meta property="og:site_name" content=" &lt;Example&gt; ">
        HTML)]);

    expect(unfurl('https://example.com/'))
        ->title->toBe("Tom & Jerry's new show")
        ->description->toBe('Line one line two')
        ->site_name->toBe('<Example>');
});

it('truncates metadata to fit', function (): void {
    $title = str_repeat('é', 400);
    $description = str_repeat('ü', 600);
    $siteName = str_repeat('ß', 150);

    Http::fake(['https://example.com/*' => htmlResponse(<<<HTML
        <meta property="og:title" content="{$title}">
        <meta property="og:description" content="{$description}">
        <meta property="og:site_name" content="{$siteName}">
        HTML)]);

    expect(unfurl('https://example.com/'))
        ->title->toBe(str_repeat('é', 299).'…')
        ->description->toBe(str_repeat('ü', 499).'…')
        ->site_name->toBe(str_repeat('ß', 99).'…');
});

it('reads no more than the first megabyte of a page', function (): void {
    Http::fake(['https://example.com/*' => htmlResponse(
        '<title>Top of the page</title><!--'.str_repeat('x', 1_048_576).'--><meta property="og:description" content="Too far down">',
    )]);

    expect(unfurl('https://example.com/'))
        ->title->toBe('Top of the page')
        ->description->toBeNull();
});

it('uses the part of a page that arrived before the transfer was cut off', function (): void {
    Http::fake(fn (Request $request): PromiseInterface => Create::rejectionFor(new ResponseException(
        'The transfer was aborted by the progress callback',
        $request->toPsrRequest(),
        new PsrResponse(200, ['Content-Type' => 'text/html'], '<title>A long page</title><p>'.str_repeat('x', 1024)),
    )));

    expect(unfurl('https://example.com/')->title)->toBe('A long page');
});

it('refuses to fetch urls that are not public http urls', function (string $url): void {
    Http::fake();

    $linkPreview = unfurl($url);

    Http::assertNothingSent();

    expectNoMetadata($linkPreview);
})->with([
    'loopback' => 'http://127.0.0.1/',
    'loopback in decimal' => 'http://2130706433/',
    'private network' => 'http://10.0.0.1/',
    'home network' => 'http://192.168.1.1/',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'carrier-grade nat' => 'http://100.64.0.1/',
    'benchmarking' => 'http://198.18.0.1/',
    'unspecified' => 'http://0.0.0.0/',
    'multicast' => 'http://224.0.0.1/',
    'ipv6 loopback' => 'http://[::1]/',
    'ipv6 unique local' => 'http://[fc00::1]/',
    'ipv6 link-local' => 'http://[fe80::1]/',
    'ipv4-mapped ipv6' => 'http://[::ffff:127.0.0.1]/',
    'nat64' => 'http://[64:ff9b::a00:1]/',
    'ipv6 multicast' => 'http://[ff02::1]/',
    'localhost' => 'http://localhost/',
    'host resolving to a private address' => 'https://internal.example.com/',
    'host resolving partly to a private address' => 'https://mixed.example.com/',
    'host resolving to nothing' => 'https://nowhere.example.com/',
    'non-default port' => 'https://example.com:8080/',
    'credentials' => 'https://user:secret@example.com/',
    'username only' => 'https://user@example.com/',
    'password only' => 'https://:secret@example.com/',
    'ftp' => 'ftp://example.com/file',
    'file' => 'file:///etc/passwd',
    'not a url' => 'https://exa mple.com/',
]);

it('follows redirects to public hosts', function (): void {
    $connections = [];

    Http::fake(function (Request $request, array $options) use (&$connections): PromiseInterface {
        $connections[] = $options['curl'][CURLOPT_CONNECT_TO][0];

        return match ($request->url()) {
            'https://example.com/old' => Http::response(status: 301, headers: ['Location' => 'https://www.example.com/blog/new']),
            default => htmlResponse('<meta property="og:title" content="Moved"><meta property="og:image" content="og.png">'),
        };
    });

    $linkPreview = unfurl('https://example.com/old');

    expect($linkPreview)
        ->url->toBe('https://example.com/old')
        ->title->toBe('Moved')
        ->image_url->toBe('https://www.example.com/blog/og.png')
        ->and($connections)->toBe(['::93.184.216.34:443', '::93.184.216.35:443']);

    Http::assertSentCount(2);
});

it('follows relative redirects', function (): void {
    Http::fake([
        'https://example.com/old' => Http::response(status: 302, headers: ['Location' => '/new']),
        'https://example.com/new' => htmlResponse('<title>New home</title>'),
    ]);

    expect(unfurl('https://example.com/old')->title)->toBe('New home');
});

it('refuses to follow redirects to urls it would not fetch', function (string $location): void {
    Http::fake(['https://example.com/*' => Http::response(status: 302, headers: ['Location' => $location])]);

    $linkPreview = unfurl('https://example.com/');

    Http::assertSentCount(1);

    expectNoMetadata($linkPreview);
})->with([
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'loopback' => 'http://127.0.0.1/',
    'ipv6 loopback' => 'http://[::1]/',
    'host resolving to a private address' => 'https://internal.example.com/',
    'non-http scheme' => 'gopher://example.com/',
    'not a url' => 'https://exa mple.com/',
]);

it('follows up to three redirects', function (): void {
    Http::fake([
        'https://example.com/1' => Http::response(status: 302, headers: ['Location' => '/2']),
        'https://example.com/2' => Http::response(status: 302, headers: ['Location' => '/3']),
        'https://example.com/3' => Http::response(status: 302, headers: ['Location' => '/4']),
        'https://example.com/4' => htmlResponse('<title>Arrived</title>'),
    ]);

    expect(unfurl('https://example.com/1')->title)->toBe('Arrived');

    Http::assertSentCount(4);
});

it('gives up after more than three redirects', function (): void {
    Http::fake([
        'https://example.com/1' => Http::response(status: 302, headers: ['Location' => '/2']),
        'https://example.com/2' => Http::response(status: 302, headers: ['Location' => '/3']),
        'https://example.com/3' => Http::response(status: 302, headers: ['Location' => '/4']),
        'https://example.com/4' => Http::response(status: 302, headers: ['Location' => '/5']),
        'https://example.com/5' => htmlResponse('<title>Too far</title>'),
    ]);

    expectNoMetadata(unfurl('https://example.com/1'));

    Http::assertSentCount(4);
    Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://example.com/5');
});

it('throws without marking the link as fetched when the page may be reachable later', function (Closure|PromiseInterface $response): void {
    Http::fake(['https://example.com/*' => $response]);

    $linkPreview = LinkPreview::factory()->create(['url' => 'https://example.com/']);

    expect(fn () => resolve(UpdateLinkPreview::class)->handle($linkPreview))->toThrow(HttpClientException::class)
        ->and($linkPreview->refresh()->fetched_at)->toBeNull();
})->with([
    'failed connection' => fn (): Closure => Http::failedConnection(),
    'too many requests' => fn (): PromiseInterface => htmlResponse('<title>Slow down</title>', 429),
    'server error' => fn (): PromiseInterface => htmlResponse('<title>Something went wrong</title>', 500),
    'service unavailable' => fn (): PromiseInterface => htmlResponse('<title>Down for maintenance</title>', 503),
]);

it('throws unexpected errors without marking the link as fetched', function (): void {
    Http::fake();

    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            throw new RuntimeException('The resolver broke.');
        }
    });

    $linkPreview = LinkPreview::factory()->create(['url' => 'https://example.com/']);

    expect(fn () => resolve(UpdateLinkPreview::class)->handle($linkPreview))->toThrow(RuntimeException::class, 'The resolver broke.')
        ->and($linkPreview->refresh()->fetched_at)->toBeNull();
});
