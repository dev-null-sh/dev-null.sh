<?php

declare(strict_types=1);

use App\Actions\FindFirstUrl;

it('finds no url in plain text', function (string $text): void {
    $action = resolve(FindFirstUrl::class);

    expect($action->handle($text))->toBeNull();
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
    $action = resolve(FindFirstUrl::class);

    expect($action->handle($text))->toBe($url);
})->with([
    'https' => ['Read https://laravel.com/docs/13.x/queues for more', 'https://laravel.com/docs/13.x/queues'],
    'http' => ['Old blog at http://example.com/blog?page=2#top', 'http://example.com/blog?page=2#top'],
    'uppercase scheme' => ['HTTPS://Example.com/Path', 'HTTPS://Example.com/Path'],
    'whole text' => ['https://github.com/laravel/framework', 'https://github.com/laravel/framework'],
    'after a newline' => ["Release notes:\nhttps://example.com/notes", 'https://example.com/notes'],
    'in angle brackets' => ['<https://example.com/a>', 'https://example.com/a'],
]);

it('finds the first of several urls', function (): void {
    $action = resolve(FindFirstUrl::class);

    expect($action->handle('Compare https://first.example.com with https://second.example.com'))
        ->toBe('https://first.example.com');
});

it('strips trailing punctuation', function (string $text, string $url): void {
    $action = resolve(FindFirstUrl::class);

    expect($action->handle($text))->toBe($url);
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
    'keeps inner punctuation' => ['https://example.com/a.b,c;d:e!f?g', 'https://example.com/a.b,c;d:e!f?g'],
]);

it('keeps closing brackets that are balanced inside the url', function (string $text, string $url): void {
    $action = resolve(FindFirstUrl::class);

    expect($action->handle($text))->toBe($url);
})->with([
    'balanced parentheses' => ['(see https://en.wikipedia.org/wiki/Foo_(bar))', 'https://en.wikipedia.org/wiki/Foo_(bar)'],
    'balanced parentheses at the end' => ['https://en.wikipedia.org/wiki/Foo_(bar)', 'https://en.wikipedia.org/wiki/Foo_(bar)'],
    'unbalanced parenthesis' => ['(see https://example.com/docs)', 'https://example.com/docs'],
    'unbalanced parenthesis before a full stop' => ['(see https://example.com/docs).', 'https://example.com/docs'],
    'balanced brackets' => ['https://example.com/?filter[status]=open', 'https://example.com/?filter[status]=open'],
    'unbalanced bracket' => ['[https://example.com/docs]', 'https://example.com/docs'],
]);

it('skips urls longer than 2048 characters', function (): void {
    $action = resolve(FindFirstUrl::class);

    $tooLong = 'https://example.com/'.str_repeat('a', 2029);
    $longest = 'https://example.com/'.str_repeat('a', 2028);

    expect(mb_strlen($longest))->toBe(2048)
        ->and($action->handle($tooLong))->toBeNull()
        ->and($action->handle($longest))->toBe($longest)
        ->and($action->handle($tooLong.' https://example.com/short'))->toBe('https://example.com/short');
});

it('finds bare domains and links them over https', function (string $text, string $url): void {
    $action = resolve(FindFirstUrl::class);

    expect($action->handle($text))->toBe($url);
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
    $action = resolve(FindFirstUrl::class);

    expect($action->handle($text))->toBe($url);
})->with([
    'full stop' => ['see nativephp.com.', 'https://nativephp.com'],
    'comma' => ['nativephp.com, laravel.com', 'https://nativephp.com'],
    'question mark' => ['Have you seen nativephp.com?', 'https://nativephp.com'],
    'quotes' => ['"nativephp.com"', 'https://nativephp.com'],
    'unbalanced parenthesis after a path' => ['(see nativephp.com/docs).', 'https://nativephp.com/docs'],
    'balanced parentheses in a path' => ['nativephp.com/wiki/Foo_(bar)', 'https://nativephp.com/wiki/Foo_(bar)'],
    'unbalanced bracket after a path' => ['[nativephp.com/docs]', 'https://nativephp.com/docs'],
]);

it('does not mistake other text for a bare domain', function (string $text): void {
    $action = resolve(FindFirstUrl::class);

    expect($action->handle($text))->toBeNull();
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
    $action = resolve(FindFirstUrl::class);

    expect($action->handle($text))->toBe($url);
})->with([
    'https' => ['https://nativephp.com/docs', 'https://nativephp.com/docs'],
    'http stays http' => ['http://nativephp.com', 'http://nativephp.com'],
    'bare domain in the query' => ['https://example.com/?next=nativephp.com', 'https://example.com/?next=nativephp.com'],
]);

it('finds whichever kind of url comes first', function (string $text, string $url): void {
    $action = resolve(FindFirstUrl::class);

    expect($action->handle($text))->toBe($url);
})->with([
    'bare domain first' => ['nativephp.com and https://laravel.com', 'https://nativephp.com'],
    'scheme url first' => ['https://laravel.com and nativephp.com', 'https://laravel.com'],
]);

it('skips bare domains whose url would be longer than 2048 characters', function (): void {
    $action = resolve(FindFirstUrl::class);

    $tooLong = 'example.com/'.str_repeat('a', 2029);
    $longest = 'example.com/'.str_repeat('a', 2028);

    expect(mb_strlen('https://'.$longest))->toBe(2048)
        ->and($action->handle($tooLong))->toBeNull()
        ->and($action->handle($longest))->toBe('https://'.$longest)
        ->and($action->handle($tooLong.' nativephp.com'))->toBe('https://nativephp.com');
});
