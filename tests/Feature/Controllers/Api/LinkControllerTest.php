<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Models\User;
use App\Services\LinkPages;

function apiLinkPage(string $url): string
{
    $page = resolve(LinkPages::class)->url($url);
    assert(is_string($page));

    return url('api/v1/links/'.mb_substr($page, mb_strlen(url('link/'))));
}

it('responds with the approved commits about a link and what its page shows', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://wiki.php.net/rfc/pipe-operator']);
    $approved = Commit::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->pending()->create(['link_preview_id' => $link->id]);

    $this->getJson(apiLinkPage($link->url))
        ->assertOk()
        ->assertJsonPath('url', 'https://wiki.php.net/rfc/pipe-operator')
        ->assertJsonPath('page_url', url('link/wiki.php.net/rfc/pipe-operator'))
        ->assertJsonPath('link.url', 'https://wiki.php.net/rfc/pipe-operator')
        ->assertJsonPath('stats', ['commits' => 1, 'people' => 1])
        ->assertJsonPath('sort', 'top')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $approved->id)
        ->assertJsonMissingPath('data.0.starred');
});

it('lists the most starred commits first, then the newest, or the newest first', function (): void {
    $link = LinkPreview::factory()->create(['url' => 'https://dev-null.sh']);
    $newest = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subHour()]);
    $favourite = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subDays(2)]);
    $older = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subDay()]);
    $favourite->stars()->attach(User::factory()->count(2)->create());

    expect($this->getJson(apiLinkPage($link->url))->assertJsonPath('sort', 'top')->json('data.*.id'))
        ->toBe([$favourite->id, $newest->id, $older->id])
        ->and($this->getJson(apiLinkPage($link->url).'?sort=latest')->assertJsonPath('sort', 'latest')->json('data.*.id'))
        ->toBe([$newest->id, $older->id, $favourite->id]);
});

it('shows a signed request the commits as the web shows them to that user', function (): void {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    $link = LinkPreview::factory()->create(['url' => 'https://dev-null.sh']);
    $starred = Commit::factory()->create(['link_preview_id' => $link->id]);
    $starred->stars()->attach($viewer);
    Commit::factory()->for($blocked)->create(['link_preview_id' => $link->id]);

    signedApiRequest(apiKeyPairFor($viewer), 'GET', apiLinkPage($link->url))
        ->assertOk()
        ->assertJsonPath('stats', ['commits' => 1, 'people' => 1])
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $starred->id)
        ->assertJsonPath('data.0.starred', true);
});

it('reads a link whose query, fragment and ending slash its page escapes', function (): void {
    $link = LinkPreview::factory()->create(['url' => 'https://example.com/docs/?page=2&q=100%25#top']);
    Commit::factory()->create(['link_preview_id' => $link->id]);
    $folder = LinkPreview::factory()->create(['url' => 'https://example.com/docs/']);
    Commit::factory()->create(['link_preview_id' => $folder->id]);

    $this->getJson(apiLinkPage($link->url))->assertOk()
        ->assertJsonPath('url', 'https://example.com/docs/?page=2&q=100%25#top')
        ->assertJsonCount(1, 'data');

    $this->getJson(apiLinkPage($folder->url))->assertOk()
        ->assertJsonPath('url', 'https://example.com/docs/')
        ->assertJsonCount(1, 'data');
});

it('answers a link written another way with the link as commits store it', function (): void {
    $link = LinkPreview::factory()->create(['url' => 'https://wiki.php.net/rfc/pipe-operator']);
    Commit::factory()->create(['link_preview_id' => $link->id]);

    $this->getJson(url('api/v1/links/WIKI.php.net/rfc/pipe-operator'))
        ->assertOk()
        ->assertJsonPath('url', 'https://wiki.php.net/rfc/pipe-operator')
        ->assertJsonPath('page_url', url('link/wiki.php.net/rfc/pipe-operator'))
        ->assertJsonCount(1, 'data');
});

it('shows no commits for a link nobody used as their card', function (): void {
    $this->getJson(url('api/v1/links/example.com/nothing'))
        ->assertOk()
        ->assertJsonPath('link', null)
        ->assertJsonPath('stats', ['commits' => 0, 'people' => 0])
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.next_cursor', null);
});

it('shows nothing of a card the viewer may see no commit use', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://example.com/private', 'title' => 'A private plan']);
    Message::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->pending()->create(['link_preview_id' => $link->id]);

    $this->getJson(apiLinkPage($link->url))
        ->assertOk()
        ->assertDontSee('A private plan')
        ->assertJsonPath('link', null)
        ->assertJsonPath('stats', ['commits' => 0, 'people' => 0])
        ->assertJsonCount(0, 'data');
});

it('rejects a sort it does not know', function (): void {
    $this->getJson(url('api/v1/links/dev-null.sh').'?sort=hot')->assertUnprocessable()->assertJsonValidationErrors('sort');
});

it('pages the commits twenty at a time', function (): void {
    $link = LinkPreview::factory()->create(['url' => 'https://dev-null.sh']);
    Commit::factory()->count(21)->create(['link_preview_id' => $link->id]);

    $response = $this->getJson(apiLinkPage($link->url))
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('stats.commits', 21);

    expect($response->json('meta.next_cursor'))->toBeString();
});
