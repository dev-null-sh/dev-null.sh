<?php

declare(strict_types=1);

use App\Dto\GitHubPermalink;
use App\Services\CodeSnippets;

function snippetUrl(string $path = 'src/Foo.php', string $lines = 'L2-L3'): string
{
    return "https://github.com/acme/app/blob/3f732656b1b97de366bce8ed147077ed11e25248/{$path}#{$lines}";
}

function putSnippet(string $url, string $file): ?array
{
    $snippets = resolve(CodeSnippets::class);
    $snippets->put(GitHubPermalink::parse($url), $file);

    return $snippets->for($url);
}

it('highlights the cited lines with their line numbers in the file', function (): void {
    expect(putSnippet(snippetUrl(), "<?php\n\$a = 1;\n// done\n\$b = 2;"))->toBe([
        'path' => 'src/Foo.php',
        'start_line' => 2,
        'end_line' => 3,
        'cited_end_line' => 3,
        'html' => "<span class=\"hl-gutter\">2</span><span class=\"hl-variable\">\$a</span> = 1;\n<span class=\"hl-gutter\">3</span><span class=\"hl-comment\">// done</span>",
    ]);
});

it('escapes the code and treats unknown file types as plain text', function (): void {
    expect(putSnippet(snippetUrl('notes.unknown', 'L1'), '<script>alert(1)</script>')['html'])
        ->toBe('<span class="hl-gutter">1</span>&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('keeps the first 30 lines of a longer range', function (): void {
    $snippet = putSnippet(snippetUrl('a.txt', 'L5-L100'), implode("\n", range(1, 200)));

    expect($snippet['start_line'])->toBe(5)
        ->and($snippet['end_line'])->toBe(5 + CodeSnippets::MAX_LINES - 1)
        ->and($snippet['cited_end_line'])->toBe(100)
        ->and(mb_substr_count($snippet['html'], "\n"))->toBe(CodeSnippets::MAX_LINES - 1);
});

it('stops at the end of the file', function (): void {
    expect(putSnippet(snippetUrl('a.txt', 'L2-L10'), "one\r\ntwo\r\nthree"))->toMatchArray(['start_line' => 2, 'end_line' => 3]);
});

it('cuts very long lines short', function (): void {
    expect(putSnippet(snippetUrl('a.txt', 'L1'), str_repeat('x', 1000))['html'])
        ->toBe('<span class="hl-gutter">1</span>'.str_repeat('x', 300).'…');
});

it('reads the cited lines of a file that was cut off in the middle of a character', function (): void {
    expect(putSnippet(snippetUrl('a.txt', 'L1'), "first\nsecond \xE2\x82")['html'])->toBe('<span class="hl-gutter">1</span>first');
});

it('shares the snippet between links written differently', function (): void {
    putSnippet(snippetUrl('a.txt', 'L1'), 'one');

    expect(resolve(CodeSnippets::class)->for('http://www.github.com/acme/app/blob/3f732656b1b97de366bce8ed147077ed11e25248/a.txt?plain=1#L1'))->not->toBeNull();
});

it('keeps nothing for lines past the end, binary files and invalid text', function (string $file, string $lines): void {
    expect(putSnippet(snippetUrl('a.txt', $lines), $file))->toBeNull();
})->with([
    'past the end' => ["one\ntwo", 'L3'],
    'binary' => ["PNG\0\0data", 'L1'],
    'invalid utf-8' => ["\xff\xfe", 'L1'],
]);

it('has no snippet for links that were never fetched or are not permalinks', function (string $url): void {
    expect(resolve(CodeSnippets::class)->for($url))->toBeNull();
})->with([
    'not fetched' => [snippetUrl()],
    'not a permalink' => ['https://example.com/'],
]);
