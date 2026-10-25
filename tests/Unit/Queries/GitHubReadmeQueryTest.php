<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\GitHubReadmeQuery;
use Illuminate\Http\Client\Request;
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

it('asks GitHub for the readme as html only', function (): void {
    $user = User::factory()->create();

    fakeGitHubReadme($user, Http::response('<p>Hi</p>'));

    resolve(GitHubReadmeQuery::class)->get($user);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/octocat/octocat/readme'
        && $request->header('Accept') === ['application/vnd.github.html+json']
        && $request->hasHeader('X-GitHub-Api-Version', '2022-11-28'));
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

it('remembers a failed read for a few minutes only', function (): void {
    $user = User::factory()->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['login' => 'octocat']),
        'api.github.com/repos/octocat/octocat/readme' => Http::sequence()->pushStatus(502)->push('<p>Hi</p>'),
    ]);

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBeFalse()
        ->and(resolve(GitHubReadmeQuery::class)->get($user))->toBeFalse();
    Http::assertSentCount(2);

    $this->travel(5)->minutes();

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBe('<p>Hi</p>');
});

it('keeps a readme with uploaded images for a few minutes, as their links expire', function (string $html, int $minutes, bool $isFetchedAgain): void {
    $user = User::factory()->create();

    fakeGitHubReadme($user, Http::response($html));

    resolve(GitHubReadmeQuery::class)->get($user);
    $this->travel($minutes)->minutes();
    resolve(GitHubReadmeQuery::class)->get($user);

    Http::assertSentCount($isFetchedAgain ? 4 : 2);
})->with([
    'uploaded image, after 4 minutes' => ['<img src="https://private-user-images.githubusercontent.com/1/a.png?jwt=x">', 4, true],
    'uploaded image, after 3 minutes' => ['<img src="https://private-user-images.githubusercontent.com/1/a.png?jwt=x">', 3, false],
    'proxied image, after 4 minutes' => ['<img src="https://camo.githubusercontent.com/abc">', 4, false],
]);

it('keeps only the attributes a readme is written with', function (): void {
    $user = User::factory()->create();

    fakeGitHubReadme($user, Http::response(<<<'HTML'
        <p align="center"><img src="https://camo.githubusercontent.com/a" srcset="https://tracker.example.com/1x.png 1x" name="getElementById" width="100" alt="badge"></p>
        <dialog open>Sign in again</dialog>
        <h3><g-emoji alias="wave">👋</g-emoji> Hi</h3>
        <map name="m"><area href="https://example.com" coords="0,0,1,1"></map>
        HTML));

    expect(resolve(GitHubReadmeQuery::class)->get($user))
        ->toContain('<p align="center"><img src="https://camo.githubusercontent.com/a" width="100" alt="badge" /></p>')
        ->toContain('<h3>👋 Hi</h3>')
        ->not->toContain('srcset')
        ->not->toContain('tracker.example.com')
        ->not->toContain('name=')
        ->not->toContain('dialog')
        ->not->toContain('Sign in again')
        ->not->toContain('area');
});

it('keeps the words of the rarer text elements, without the elements', function (): void {
    $user = User::factory()->create();

    fakeGitHubReadme($user, Http::response(<<<'HTML'
        <p><samp>A resourceful programmer</samp> with <ins>five</ins> years of <abbr title="x">PHP</abbr>.</p>
        <dl><dt>Stack</dt><dd>Laravel</dd></dl>
        <figure><img src="https://camo.githubusercontent.com/a" alt="chart"><figcaption>Commits</figcaption></figure>
        <svg><text>secret</text></svg><template><p>hidden</p></template>
        HTML));

    expect(resolve(GitHubReadmeQuery::class)->get($user))
        ->toContain('<p>A resourceful programmer with five years of PHP.</p>')
        ->toContain('StackLaravel')
        ->toContain('<img src="https://camo.githubusercontent.com/a" alt="chart" />Commits')
        ->not->toContain('samp')
        ->not->toContain('<dl')
        ->not->toContain('secret')
        ->not->toContain('hidden');
});

it('shows no readme for a github account that no longer exists', function (): void {
    $user = User::factory()->create();

    Http::fake(["api.github.com/user/{$user->github_id}" => Http::response(status: 404)]);

    expect(resolve(GitHubReadmeQuery::class)->get($user))->toBeNull();
});
