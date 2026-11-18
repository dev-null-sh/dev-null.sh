<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Models\User;
use App\Services\CanonicalUrls;
use App\Services\LinkPages;
use App\Services\Urls;

function linkPage(string $url): string
{
    $page = resolve(LinkPages::class)->url($url);
    assert(is_string($page));

    return $page;
}

it('shows guests the approved commits about a link', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://wiki.php.net/rfc/pipe-operator']);
    $approved = Commit::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->pending()->create(['link_preview_id' => $link->id]);

    $this->get(linkPage($link->url))->assertOk()->assertInertia(fn ($page) => $page
        ->component('link/show')
        ->where('url', 'https://wiki.php.net/rfc/pipe-operator')
        ->where('pageUrl', url('link/wiki.php.net/rfc/pipe-operator'))
        ->where('link.url', 'https://wiki.php.net/rfc/pipe-operator')
        ->where('stats', ['commits' => 1, 'people' => 1])
        ->has('commits.data', 1)
        ->where('commits.data.0.id', $approved->id));
});

it('lists the most starred commits first, then the newest, or the newest first', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh']);
    $newest = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subHour()]);
    $favourite = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subDays(2)]);
    $older = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subDay()]);
    $favourite->stars()->attach(User::factory()->count(2)->create());
    $this->actingAs(User::factory()->create());

    $this->get(linkPage($link->url))->assertInertia(fn ($page) => $page
        ->where('sort', 'top')
        ->where('commits.data', fn ($commits): bool => collect($commits)->pluck('id')->all() === [$favourite->id, $newest->id, $older->id]));

    $this->get(linkPage($link->url).'?sort=latest')->assertInertia(fn ($page) => $page
        ->where('sort', 'latest')
        ->where('commits.data', fn ($commits): bool => collect($commits)->pluck('id')->all() === [$newest->id, $older->id, $favourite->id]));
});

it('hides the commits the viewer may not see', function (): void {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    $link = LinkPreview::factory()->create(['url' => 'https://dev-null.sh']);
    $visibleAuthor = User::factory()->create();
    $visible = Commit::factory()->for($visibleAuthor)->create(['link_preview_id' => $link->id]);
    $alsoVisible = Commit::factory()->for($visibleAuthor)->create(['link_preview_id' => $link->id]);
    Commit::factory()->count(2)->for($blocked)->create(['link_preview_id' => $link->id]);
    Commit::factory()->pending()->create(['link_preview_id' => $link->id]);
    $own = Commit::factory()->pending()->for($viewer)->create(['link_preview_id' => $link->id]);

    $this->actingAs($viewer)->get(linkPage($link->url))->assertOk()->assertInertia(fn ($page) => $page
        ->where('stats', ['commits' => 3, 'people' => 2])
        ->where('commits.data', fn ($commits): bool => collect($commits)->pluck('id')->sort()->values()->all() === collect([$visible->id, $alsoVisible->id, $own->id])->sort()->values()->all()));
});

it('reads a link whose query, fragment and ending slash its page escapes', function (): void {
    $link = LinkPreview::factory()->create(['url' => 'https://example.com/docs/?page=2&q=100%25']);
    Commit::factory()->create(['link_preview_id' => $link->id]);

    expect(linkPage($link->url))->toBe(url('link/example.com/docs/%3Fpage=2&q=100%2525'));
    expect(linkPage('https://php.net/archive/2026.php#2026-11-18-1'))->toBe(url('link/php.net/archive/2026.php%232026-11-18-1'));

    $this->get(linkPage($link->url))->assertOk()->assertInertia(fn ($page) => $page
        ->where('url', 'https://example.com/docs/?page=2&q=100%25')
        ->has('commits.data', 1));

    $page = url('link/example.com/docs%2F');

    $this->get($page)->assertOk()->assertInertia(fn ($page) => $page->where('url', 'https://example.com/docs/'));
});

it('reads a link whose canonical url grew longer than the links commits may have', function (): void {
    $link = LinkPreview::factory()->create(['url' => resolve(CanonicalUrls::class)->canonical('https://example.com/'.str_repeat('é', 1000))]);
    Commit::factory()->create(['link_preview_id' => $link->id]);

    expect(mb_strlen($link->url))->toBeGreaterThan(Urls::MAX_LENGTH);

    $this->get(linkPage($link->url))->assertOk()->assertInertia(fn ($page) => $page->where('url', $link->url)->has('commits.data', 1));
});

it('sends a link written another way to the page of the link as commits store it', function (): void {
    $this->get(url('link/WIKI.php.net/rfc/pipe-operator').'?sort=latest')
        ->assertRedirect(url('link/wiki.php.net/rfc/pipe-operator').'?sort=latest')
        ->assertStatus(301);
});

it('shows no commits for a link nobody used as their card', function (): void {
    $this->get(url('link/example.com/nothing'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('link', null)
        ->where('stats', ['commits' => 0, 'people' => 0])
        ->where('commits', null));
});

it('shows nothing of a card the viewer may see no commit use', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://example.com/private', 'title' => 'A private plan']);
    Message::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->pending()->create(['link_preview_id' => $link->id]);

    $this->get(url('link/example.com/private'))
        ->assertDontSee('A private plan')
        ->assertInertia(fn ($page) => $page->where('link', null)->where('commits', null));
});

it('lets search engines index the page only once two people talk about the link', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh', 'title' => 'dev/null', 'image_url' => 'https://dev-null.sh/og.png']);
    $author = User::factory()->create();
    Commit::factory()->count(2)->for($author)->create(['link_preview_id' => $link->id]);

    $this->get(linkPage($link->url))
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('<link rel="canonical" href="'.url('link/dev-null.sh').'">', false)
        ->assertSee('<meta property="og:title" content="dev/null on /dev/null">', false)
        ->assertSee('<meta property="og:image" content="https://dev-null.sh/og.png">', false)
        ->assertDontSee('og:image:width', false)
        ->assertSee('Read 2 commits from 1 person about dev-null.sh', false);

    Commit::factory()->create(['link_preview_id' => $link->id]);

    $this->get(linkPage($link->url))
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">', false);
});

it('previews the page of a link nobody used with the site image', function (): void {
    $this->get(url('link/example.com/nothing'))
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('<meta property="og:title" content="example.com/nothing on /dev/null">', false)
        ->assertSee('<meta property="og:image" content="'.url('/og.png').'">', false)
        ->assertSee('Read 0 commits from 0 people about example.com/nothing', false);
});

it('names a link by its readable characters', function (): void {
    $this->get(url('link/fr.wikipedia.org/wiki/%25C3%2589t%25C3%25A9'))
        ->assertSee('<meta property="og:title" content="fr.wikipedia.org/wiki/Été on /dev/null">', false);
});

it('returns guests to the link page they were reading once they sign in', function (): void {
    $this->get(url('link/dev-null.sh').'?sort=latest')->assertOk();

    expect(session('url.intended'))->toBe(url('link/dev-null.sh').'?sort=latest');
});

it('rejects a sort it does not know', function (): void {
    $this->get(url('link/dev-null.sh').'?sort=hot')->assertSessionHasErrors('sort');
});
