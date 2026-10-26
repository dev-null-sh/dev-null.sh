<?php

declare(strict_types=1);

use App\Services\Urls;

it('finds no url in plain text', function (string $text): void {
    $urls = resolve(Urls::class);

    expect($urls->first($text))->toBeNull();
})->with([
    'empty' => '',
    'words' => 'Shipped the new log today',
    'file names' => 'Upgraded to Node.js 24, see README.md',
    'scheme only' => 'see https:// for details',
    'scheme and punctuation' => 'see https://. for details',
    'unsupported scheme' => 'ftp://files.example.com/release.tar.gz',
    'scheme glued to a word' => 'nothttps://example.com',
]);

it('finds http and https urls', function (string $text, string $url): void {
    $urls = resolve(Urls::class);

    expect($urls->first($text))->toBe($url);
})->with([
    'https' => ['Read https://laravel.com/docs/13.x/queues for more', 'https://laravel.com/docs/13.x/queues'],
    'http' => ['Old blog at http://example.com/blog?page=2#top', 'http://example.com/blog?page=2#top'],
    'uppercase scheme' => ['HTTPS://Example.com/Path', 'HTTPS://Example.com/Path'],
    'whole text' => ['https://github.com/laravel/framework', 'https://github.com/laravel/framework'],
    'after a newline' => ["Release notes:\nhttps://example.com/notes", 'https://example.com/notes'],
    'in angle brackets' => ['<https://example.com/a>', 'https://example.com/a'],
]);

it('finds the first of several urls', function (): void {
    $urls = resolve(Urls::class);

    expect($urls->first('Compare https://first.example.com with https://second.example.com'))
        ->toBe('https://first.example.com');
});

it('finds every url once, in order', function (): void {
    $urls = resolve(Urls::class);

    expect($urls->all('Compare https://first.example.com, nativephp.com and `https://code.example.com` with https://first.example.com'))
        ->toBe(['https://first.example.com', 'https://nativephp.com']);
});

it('finds no urls in plain text', function (): void {
    $urls = resolve(Urls::class);

    expect($urls->all('Shipped the new log today'))->toBe([]);
});

it('strips trailing punctuation', function (string $text, string $url): void {
    $urls = resolve(Urls::class);

    expect($urls->first($text))->toBe($url);
})->with([
    'full stop' => ['Shipped at https://example.com/launch.', 'https://example.com/launch'],
    'comma' => ['See https://example.com, then commit', 'https://example.com'],
    'semicolon' => ['https://example.com; done', 'https://example.com'],
    'colon' => ['Here it is https://example.com:', 'https://example.com'],
    'exclamation mark' => ['It works https://example.com!', 'https://example.com'],
    'question mark' => ['Have you seen https://example.com?', 'https://example.com'],
    'single quotes' => ["'https://example.com'", 'https://example.com'],
    'double quotes' => ['"https://example.com"', 'https://example.com'],
    'several' => ['(https://example.com/a?b=c!?).', 'https://example.com/a?b=c'],
    'ellipsis' => ['Read https://example.com/launch…', 'https://example.com/launch'],
    'curly double quotes' => ['He said “https://example.com/launch”.', 'https://example.com/launch'],
    'curly single quotes' => ['‘https://example.com/launch’', 'https://example.com/launch'],
    'keeps inner curly quotes and ellipses' => ['https://example.com/it’s…ok', 'https://example.com/it’s…ok'],
    'keeps inner punctuation' => ['https://example.com/a.b,c;d:e!f?g', 'https://example.com/a.b,c;d:e!f?g'],
]);

it('keeps closing brackets that are balanced inside the url', function (string $text, string $url): void {
    $urls = resolve(Urls::class);

    expect($urls->first($text))->toBe($url);
})->with([
    'balanced parentheses' => ['(see https://en.wikipedia.org/wiki/Foo_(bar))', 'https://en.wikipedia.org/wiki/Foo_(bar)'],
    'balanced parentheses at the end' => ['https://en.wikipedia.org/wiki/Foo_(bar)', 'https://en.wikipedia.org/wiki/Foo_(bar)'],
    'unbalanced parenthesis' => ['(see https://example.com/docs)', 'https://example.com/docs'],
    'unbalanced parenthesis before a full stop' => ['(see https://example.com/docs).', 'https://example.com/docs'],
    'balanced brackets' => ['https://example.com/?filter[status]=open', 'https://example.com/?filter[status]=open'],
    'unbalanced bracket' => ['[https://example.com/docs]', 'https://example.com/docs'],
]);

it('skips urls longer than 2048 characters', function (): void {
    $urls = resolve(Urls::class);

    $tooLong = 'https://example.com/'.str_repeat('a', 2029);
    $longest = 'https://example.com/'.str_repeat('a', 2028);

    expect(mb_strlen($longest))->toBe(2048)
        ->and($urls->first($tooLong))->toBeNull()
        ->and($urls->first($longest))->toBe($longest)
        ->and($urls->first($tooLong.' https://example.com/short'))->toBe('https://example.com/short');
});

it('finds bare domains and links them over https', function (string $text, string $url): void {
    $urls = resolve(Urls::class);

    expect($urls->first($text))->toBe($url);
})->with([
    'domain' => ['nativephp.com', 'https://nativephp.com'],
    'in a sentence' => ['Moved the docs to devnull.com today', 'https://devnull.com'],
    'subdomains and a path' => ['Read docs.github.com/en for details', 'https://docs.github.com/en'],
    'second-level ccTLD with path, query and fragment' => ['www.example.co.uk/path?x=1#y', 'https://www.example.co.uk/path?x=1#y'],
    'uppercase' => ['NATIVEPHP.COM', 'https://NATIVEPHP.COM'],
    'uppercase top-level domain' => ['EXAMPLE.COM', 'https://EXAMPLE.COM'],
    'mixed-case labels' => ['NativePHP.com', 'https://NativePHP.com'],
    'hyphenated label' => ['my-site.dev', 'https://my-site.dev'],
    'digits' => ['web3.io', 'https://web3.io'],
    'after an opening bracket' => ['(nativephp.com)', 'https://nativephp.com'],
    'in brackets' => ['(see nativephp.com)', 'https://nativephp.com'],
    'after a newline' => ["Docs:\nlaravel.cloud", 'https://laravel.cloud'],
]);

it('strips trailing punctuation from bare domains', function (string $text, string $url): void {
    $urls = resolve(Urls::class);

    expect($urls->first($text))->toBe($url);
})->with([
    'full stop' => ['see nativephp.com.', 'https://nativephp.com'],
    'comma' => ['nativephp.com, laravel.com', 'https://nativephp.com'],
    'question mark' => ['Have you seen nativephp.com?', 'https://nativephp.com'],
    'quotes' => ['"nativephp.com"', 'https://nativephp.com'],
    'ellipsis after a path' => ['see nativephp.com/docs…', 'https://nativephp.com/docs'],
    'curly quotes after a path' => ['“nativephp.com/docs”', 'https://nativephp.com/docs'],
    'unbalanced parenthesis after a path' => ['(see nativephp.com/docs).', 'https://nativephp.com/docs'],
    'balanced parentheses in a path' => ['nativephp.com/wiki/Foo_(bar)', 'https://nativephp.com/wiki/Foo_(bar)'],
    'unbalanced bracket after a path' => ['[nativephp.com/docs]', 'https://nativephp.com/docs'],
]);

it('does not mistake other text for a bare domain', function (string $text): void {
    $urls = resolve(Urls::class);

    expect($urls->first($text))->toBeNull();
})->with([
    'email address' => 'Mail simon@nativephp.com',
    'handle' => 'Thanks @nativephp',
    'handle with a dot' => 'Thanks @nativephp.com',
    'version number' => 'Released 1.2.3 and v2.0.1',
    'markdown file' => 'README.md',
    'shell script' => 'deploy.sh',
    'rust file' => 'main.rs',
    'python file' => 'manage.py',
    'perl file' => 'build.pl',
    'javascript library' => 'Node.js',
    'php file' => 'app.php',
    'typescript file' => 'index.ts',
    'yaml file' => 'config.yml',
    'zip file' => 'release.zip',
    'property access' => 'user.id',
    'domain inside a longer word' => 'nativephp.community',
    'domain followed by a hyphen' => 'nativephp.com-preview',
    'domain followed by another label' => 'socket.io.js',
    'after a word character' => 'foo_nativephp.com',
    'after a dot' => '.nativephp.com',
    'after a slash' => 'path/nativephp.com',
    'after a hyphen' => '-nativephp.com',
    'label ending in a hyphen' => 'nativephp-.com',
    'non-ascii label' => 'café.com',
    'no top-level domain' => 'localhost:3000',
    'mixed-case top-level domain' => 'example.Com',
    'missing space after a sentence' => 'Fixed the bug.It was annoying',
    'missing space after a short sentence' => 'Done.No more flaky tests',
    'missing space before a capitalised word' => 'Shipped it.Me happy',
    'method call' => 'use array.at(-1) instead',
    'method call with an argument' => 'call str.is(x)',
    'method call on its own' => 'map.to(x)',
]);

it('prefers a url with a scheme over the bare domain inside it', function (string $text, string $url): void {
    $urls = resolve(Urls::class);

    expect($urls->first($text))->toBe($url);
})->with([
    'https' => ['https://nativephp.com/docs', 'https://nativephp.com/docs'],
    'http stays http' => ['http://nativephp.com', 'http://nativephp.com'],
    'bare domain in the query' => ['https://example.com/?next=nativephp.com', 'https://example.com/?next=nativephp.com'],
]);

it('finds whichever kind of url comes first', function (string $text, string $url): void {
    $urls = resolve(Urls::class);

    expect($urls->first($text))->toBe($url);
})->with([
    'bare domain first' => ['nativephp.com and https://laravel.com', 'https://nativephp.com'],
    'scheme url first' => ['https://laravel.com and nativephp.com', 'https://laravel.com'],
]);

it('skips bare domains whose url would be longer than 2048 characters', function (): void {
    $urls = resolve(Urls::class);

    $tooLong = 'example.com/'.str_repeat('a', 2029);
    $longest = 'example.com/'.str_repeat('a', 2028);

    expect(mb_strlen('https://'.$longest))->toBe(2048)
        ->and($urls->first($tooLong))->toBeNull()
        ->and($urls->first($longest))->toBe('https://'.$longest)
        ->and($urls->first($tooLong.' nativephp.com'))->toBe('https://nativephp.com');
});

it('skips urls in code', function (): void {
    $urls = resolve(Urls::class);

    expect($urls->first("run `curl example.com`\n```\nhttps://example.org\n```\nthen read laravel.com"))
        ->toBe('https://laravel.com')
        ->and($urls->first('only `https://example.com` here'))->toBeNull();
});

it('detects oversized prose urls including the inferred scheme', function (string $text, bool $oversized): void {
    expect(resolve(Urls::class)->hasOversizedUrl($text))->toBe($oversized);
})->with([
    'no links' => ['Hello world', false],
    '2048 characters' => ['https://example.com/'.str_repeat('x', 2028), false],
    'sentence punctuation' => ['(https://example.com/'.str_repeat('x', 2028).').', false],
    '2049 characters' => ['https://example.com/'.str_repeat('x', 2029), true],
    'bare domain at limit' => ['example.com/'.str_repeat('x', 2028), false],
    'bare domain over limit' => ['example.com/'.str_repeat('x', 2029), true],
    'second link oversized' => ['https://example.org https://example.com/'.str_repeat('x', 2029), true],
    'code is literal' => ['`https://example.com/'.str_repeat('x', 2029).'`', false],
]);

it('counts each prose link as at most 23 characters', function (string $body, int $length): void {
    expect(resolve(Urls::class)->characterCount($body))->toBe($length);
})->with([
    'unicode text' => ['é🚀 hello', 8],
    'short bare domain' => ['example.com', 11],
    'long link' => ['https://example.com/long-path-here', 23],
    'Unicode letter before a scheme' => ['éhttps://example.com/long-path-here', 35],
    'Unicode number before a scheme' => ['٤https://example.com/long-path-here', 35],
    'combining mark before a scheme' => ["\u{0301}https://example.com/long-path-here", 35],
    'connector punctuation before a scheme' => ["\u{203f}https://example.com/long-path-here", 35],
    'underscore before a scheme' => ['_https://example.com/long-path-here', 35],
    'emoji before a scheme' => ['🚀https://example.com/long-path-here', 24],
    'repeated links' => ['https://example.com/long-path-here https://example.com/long-path-here', 47],
    'punctuation' => ['(https://example.com/long-path-here).', 26],
    'balanced brackets' => ['https://example.com/a_(long_article).', 24],
    'bare domain path' => ['example.com/long-path-here', 23],
    'inline code' => ['`https://example.com/long-path-here`', 36],
    'fenced code' => ["```\nhttps://example.com/long-path-here\n```", 42],
    'unclosed code is prose' => ['`https://example.com/long-path-here', 24],
    'scheme only' => ['https://', 8],
    'scheme with punctuation' => ['https://...', 11],
    'unsupported scheme' => ['javascript:alert(1)', 19],
    'longest recognized link' => ['https://example.com/'.str_repeat('x', 2028), 23],
    'oversized link' => ['https://example.com/'.str_repeat('x', 2029), 2049],
]);

it('shortens urls for display', function (string $url, string $shortened): void {
    $urls = resolve(Urls::class);

    expect($urls->shorten($url))->toBe($shortened);
})->with([
    'long query' => ['https://github.com/dev-null-sh/dev-null.sh/pulls?q=sort%3Aupdated-desc+is%3Apr+state%3Aopen+author%3AxHeaven', 'github.com/dev-null-sh/dev-nu…'],
    'long path' => ['https://laravel.com/docs/13.x/eloquent-relationships#one-to-many-polymorphic-relations', 'laravel.com/docs/13.x/eloquent…'],
    'www' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG', 'youtube.com/watch?v=dQw4w9WgXc…'],
    'port' => ['http://localhost:8000/telescope/requests/9d1f3c2a-1b2c-4d5e-8f90', 'localhost:8000/telescope/requests…'],
    'unicode host' => ['https://münchen.de/rathaus/öffnungszeiten-und-adressen/stadtverwaltung', 'münchen.de/rathaus/öffnungsze…'],
    'unicode path' => ['https://ja.wikipedia.org/wiki/東京都の観光地一覧と歴史的建造物', 'ja.wikipedia.org/wiki/東京都の観光地一覧と歴史的…'],
    'cut before a percent sign' => ['https://example.com/abcdefghijklmnopq%3Atail', 'example.com/abcdefghijklmnopq…'],
    'cut inside a percent sequence' => ['https://example.com/search?q=laravel%2Boctane%2Bfrankenphp', 'example.com/search?q=laravel…'],
    'brackets' => ['https://en.wikipedia.org/wiki/Laravel_(web_application_framework)', 'en.wikipedia.org/wiki/Laravel_(web_…'],
    '20 characters after the host' => ['https://example.com/blog/hello-world-20', 'example.com/blog/hello-world-20'],
    '21 characters after the host' => ['https://example.com/blog/hello-world-021', 'example.com/blog/hello-world-0…'],
    'host only' => ['https://laravel.com', 'laravel.com'],
    'short path' => ['https://nativephp.com/docs', 'nativephp.com/docs'],
    'long host' => ['https://my-service.staging.eu-west-1.elb.amazonaws.com/health', 'my-service.staging.eu-west-1.elb.amazonaws.com/health'],
    'uppercase' => ['HTTPS://WWW.EXAMPLE.COM/Path/To/Some/Deeply/Nested/Page', 'EXAMPLE.COM/Path/To/Some/Deepl…'],
    'bare domain' => ['example.com', 'example.com'],
    'bare domain with a path' => ['nunomaduro.com/talks', 'nunomaduro.com/talks'],
    'bare www domain' => ['www.php.net/manual/en/function.array-map.php', 'php.net/manual/en/function…'],
    'www as the domain' => ['https://www.com/about', 'www.com/about'],
    'host with a trailing slash' => ['https://www.example.com/', 'example.com'],
    'bare domain with a trailing slash' => ['example.org/', 'example.org'],
    'port with a trailing slash' => ['http://localhost:8000/', 'localhost:8000'],
    'path with a trailing slash' => ['https://laravel.com/docs/', 'laravel.com/docs/'],
    'slash before a query' => ['https://example.com/?ref=feed', 'example.com/?ref=feed'],
    'slash ending a query' => ['https://example.com/login?next=/', 'example.com/login?next=/'],
    'slash ending a fragment' => ['https://example.com/#/', 'example.com/#/'],
    'slash before a trailing newline' => ["https://example.com/\n", "example.com/\n"],
]);

it('shortens the urls in prose', function (): void {
    $urls = resolve(Urls::class);

    expect($urls->withShortenedUrls('A lot came! https://github.com/dev-null-sh/dev-null.sh/pulls?q=sort%3Aupdated-desc. See https://laravel.com'))
        ->toBe('A lot came! github.com/dev-null-sh/dev-nu…. See laravel.com');
});

it('does not shorten what it would not link', function (string $text): void {
    $urls = resolve(Urls::class);

    expect($urls->withShortenedUrls($text))->toBe($text);
})->with([
    'inline code' => ['run `curl https://github.com/dev-null-sh/dev-null.sh/pulls?q=sort%3Aupdated-desc`'],
    'code block' => ["```\nhttps://github.com/dev-null-sh/dev-null.sh/pulls?q=sort%3Aupdated-desc\n```"],
    'scheme only' => ['see https:// for details'],
    'longer than 2048 characters' => ['https://example.com/'.str_repeat('a', 2029)],
]);
