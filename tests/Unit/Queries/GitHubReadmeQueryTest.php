<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\GitHubReadmeQuery;
use Illuminate\Support\Facades\Http;

function fakeGitHubReadme(User $user, mixed $readme): void
{
    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => 'octocat']),
        'api.github.com/repos/octocat/octocat/readme' => $readme,
    ]);
}

it('keeps the formatting, links and proxied images of the readme', function (): void {
    $user = User::factory()->create();

    fakeGitHubReadme($user, Http::response(<<<'HTML'
        <div id="readme" class="md"><article class="markdown-body">
        <h2 class="heading-element">Hi <strong>there</strong></h2>
        <p dir="auto"><a href="https://example.com">site</a> <img src="https://camo.githubusercontent.com/abc/def" alt="banner"></p>
        <markdown-accessiblity-table><table><tbody><tr><td>PHP</td></tr></tbody></table></markdown-accessiblity-table>
        </article></div>
        HTML));

    expect(resolve(GitHubReadmeQuery::class)->get($user))
        ->toContain('<h2>Hi <strong>there</strong></h2>')
        ->toContain('<a href="https://example.com" rel="noopener noreferrer nofollow ugc" target="_blank">site</a>')
        ->toContain('<img src="https://camo.githubusercontent.com/abc/def" alt="banner" />')
        ->toContain('<table><tbody><tr><td>PHP</td></tr></tbody></table>')
        ->not->toContain('class=')
        ->not->toContain('id=');
});

it('removes scripts, styles, event handlers and images from elsewhere', function (): void {
    $user = User::factory()->create();

    fakeGitHubReadme($user, Http::response(<<<'HTML'
        <p style="position:fixed;inset:0" onclick="steal()">Hi<script>steal()</script></p>
        <a href="javascript:steal()">click</a>
        <img src="https://tracker.example.com/pixel.gif">
        <img src="/relative.png">
        <picture><source srcset="https://tracker.example.com/dark.png"><img src="https://camo.githubusercontent.com/light"></picture>
        <iframe src="https://example.com"></iframe>
        HTML));

    expect(resolve(GitHubReadmeQuery::class)->get($user))
        ->toContain('<p>Hi</p>')
        ->toContain('<img src="https://camo.githubusercontent.com/light" />')
        ->not->toContain('script')
        ->not->toContain('style')
        ->not->toContain('onclick')
        ->not->toContain('javascript')
        ->not->toContain('tracker.example.com')
        ->not->toContain('relative.png')
        ->not->toContain('iframe');
});

it('remembers that there is no readme', function (): void {
    $user = User::factory()->create();

    fakeGitHubReadme($user, Http::response(status: 404));

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBeNull()
        ->and(resolve(GitHubReadmeQuery::class)->get($user))->toBeNull();
    Http::assertSentCount(2);
});

it('does not cache a failed read', function (): void {
    $user = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => 'octocat']),
        'api.github.com/repos/octocat/octocat/readme' => Http::sequence()->pushStatus(502)->push('<p>Hi</p>'),
    ]);

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBeNull()
        ->and(resolve(GitHubReadmeQuery::class)->get($user))->toBe('<p>Hi</p>');
});

it('shows no readme for a github account that no longer exists', function (): void {
    $user = User::factory()->create();

    Http::fake(["api.github.com/user/{$user->github_id}" => Http::response(status: 404)]);

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBeNull();
});
