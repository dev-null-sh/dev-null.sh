<?php

declare(strict_types=1);

use App\Dto\GitHubPermalink;

const PERMALINK_SHA = '3f732656b1b97de366bce8ed147077ed11e25248';

it('parses a permalink to lines of a file at a commit', function (string $url, string $path, int $startLine, int $endLine): void {
    $permalink = GitHubPermalink::parse($url);

    expect($permalink)->toBeInstanceOf(GitHubPermalink::class)
        ->and($permalink->owner)->toBe('laravel')
        ->and($permalink->repository)->toBe('framework')
        ->and($permalink->sha)->toBe(PERMALINK_SHA)
        ->and($permalink->path)->toBe($path)
        ->and($permalink->startLine)->toBe($startLine)
        ->and($permalink->endLine)->toBe($endLine)
        ->and($permalink->rawUrl()->toAsciiString())->toBe('https://raw.githubusercontent.com/laravel/framework/'.PERMALINK_SHA."/{$path}");
})->with([
    'range' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L10-L20', 'src/Str.php', 10, 20],
    'single line' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L7', 'src/Str.php', 7, 7],
    'same line twice' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L7-L7', 'src/Str.php', 7, 7],
    'file at the root' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/composer.json#L1-L3', 'composer.json', 1, 3],
    'www and http' => ['http://www.github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L1', 'src/Str.php', 1, 1],
    'query string' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/README.md?plain=1#L2', 'README.md', 2, 2],
    'dot segments collapsed' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/./a/../Str.php#L1', 'src/Str.php', 1, 1],
]);

it('keys the same lines the same way however the link was written', function (): void {
    $key = GitHubPermalink::parse('https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/README.md#L1-L2')->cacheKey();

    expect(GitHubPermalink::parse('http://www.github.com/laravel/framework/blob/'.PERMALINK_SHA.'/README.md?plain=1#L1-L2')->cacheKey())->toBe($key)
        ->and(GitHubPermalink::parse('https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/README.md#L1-L3')->cacheKey())->not->toBe($key);
});

it('ignores links that are not permalinks to lines of code', function (string $url): void {
    expect(GitHubPermalink::parse($url))->toBeNull();
})->with([
    'branch' => ['https://github.com/laravel/framework/blob/12.x/src/Str.php#L10-L20'],
    'short sha' => ['https://github.com/laravel/framework/blob/3f73265/src/Str.php#L10'],
    'uppercase sha' => ['https://github.com/laravel/framework/blob/'.mb_strtoupper(PERMALINK_SHA).'/src/Str.php#L10'],
    'no lines' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php'],
    'line zero' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L0'],
    'columns' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L10C2-L12C4'],
    'backwards range' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L20-L10'],
    'tree' => ['https://github.com/laravel/framework/tree/'.PERMALINK_SHA.'/src#L1'],
    'no path' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/#L1'],
    'traversal out of the blob' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/../../x#L1'],
    'encoded slash' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src%2FStr.php#L1'],
    'encoded dots' => ['https://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/%2e%2e/Str.php#L1'],
    'lookalike host' => ['https://github.com.evil.test/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L1'],
    'userinfo' => ['https://github.com@evil.test/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L1'],
    'credentials' => ['https://user:pass@github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L1'],
    'port' => ['https://github.com:8443/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L1'],
    'gist' => ['https://gist.github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L1'],
    'other scheme' => ['ftp://github.com/laravel/framework/blob/'.PERMALINK_SHA.'/src/Str.php#L1'],
    'not a url' => ['github.com/laravel/framework'],
]);
