<?php

declare(strict_types=1);

use App\Dto\GitHubReference;
use App\Enums\GitHubReferenceType;

it('recognizes links to github', function (string $url, GitHubReferenceType $type, string $label, string $apiPath): void {
    $reference = GitHubReference::tryFromUrl($url);

    expect($reference?->type)->toBe($type)
        ->and($reference?->label())->toBe($label)
        ->and($reference?->apiPath())->toBe($apiPath);
})->with([
    'repository' => ['https://github.com/laravel/framework', GitHubReferenceType::Repository, 'laravel/framework', 'repos/laravel/framework'],
    'issue' => ['https://github.com/laravel/framework/issues/5123', GitHubReferenceType::Issue, 'laravel/framework#5123', 'repos/laravel/framework/issues/5123'],
    'pull request' => ['https://github.com/laravel/framework/pull/5124', GitHubReferenceType::PullRequest, 'laravel/framework#5124', 'repos/laravel/framework/pulls/5124'],
    'pull request tab' => ['https://github.com/laravel/framework/pull/5124/files', GitHubReferenceType::PullRequest, 'laravel/framework#5124', 'repos/laravel/framework/pulls/5124'],
    'release' => ['https://github.com/laravel/framework/releases/tag/v13.0.0', GitHubReferenceType::Release, 'laravel/framework@v13.0.0', 'repos/laravel/framework/releases/tags/v13.0.0'],
    'commit' => ['https://github.com/laravel/framework/commit/42faad4cfa50bcaa619b55ce5db7704ea673e2aa', GitHubReferenceType::Commit, 'laravel/framework@42faad4', 'repos/laravel/framework/commits/42faad4cfa50bcaa619b55ce5db7704ea673e2aa'],
    'www, query and fragment' => ['https://www.github.com/nunomaduro/devnull.com/issues/14?x=1#issuecomment-1', GitHubReferenceType::Issue, 'nunomaduro/devnull.com#14', 'repos/nunomaduro/devnull.com/issues/14'],
    'lines of code' => ['https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Application.php#L10-L20', GitHubReferenceType::Code, 'laravel/framework', 'repos/laravel/framework/contents/src/Illuminate/Foundation/Application.php'],
]);

it('reads the file and lines of a link to code', function (string $url, string $ref, int $firstLine, int $lastLine, string $lines): void {
    $reference = GitHubReference::tryFromUrl($url);

    expect($reference)
        ->id->toBe($ref)
        ->firstLine->toBe($firstLine)
        ->lastLine->toBe($lastLine)
        ->and($reference?->lines())->toBe($lines);
})->with([
    'a range' => ['https://github.com/laravel/framework/blob/13.x/src/Foo.php#L10-L20', '13.x', 10, 20, 'src/Foo.php#L10-L20'],
    'one line' => ['https://github.com/laravel/framework/blob/42faad4/src/Foo.php#L7', '42faad4', 7, 7, 'src/Foo.php#L7'],
    'a reversed range' => ['https://github.com/laravel/framework/blob/main/src/Foo.php#L20-L10', 'main', 20, 20, 'src/Foo.php#L20'],
    'an encoded path' => ['https://github.com/laravel/framework/blob/main/docs/My%20Notes.md#L1-L2', 'main', 1, 2, 'docs/My Notes.md#L1-L2'],
]);

it('highlights code as the language of its file', function (string $path, ?string $language): void {
    expect(GitHubReference::tryFromUrl("https://github.com/laravel/framework/blob/main/{$path}#L1")?->language())->toBe($language);
})->with([
    'an extension' => ['src/Foo.php', 'php'],
    'an extension with another name' => ['docker-compose.yml', 'yaml'],
    'blade' => ['resources/views/welcome.blade.php', 'blade'],
    'a dockerfile' => ['Dockerfile', 'dockerfile'],
    'an extension the highlighter does not know' => ['src/main.rs', 'rs'],
    'no extension' => ['Makefile', null],
]);

it('ignores other links', function (string $url): void {
    expect(GitHubReference::tryFromUrl($url))->toBeNull();
})->with([
    'another host' => 'https://gitlab.com/laravel/framework',
    'a look-alike host' => 'https://github.com.evil.test/laravel/framework',
    'a profile' => 'https://github.com/laravel',
    'a file without lines' => 'https://github.com/laravel/framework/blob/13.x/README.md',
    'a file with another anchor' => 'https://github.com/laravel/framework/blob/13.x/README.md#installation',
    'not a url' => 'laravel/framework',
]);
