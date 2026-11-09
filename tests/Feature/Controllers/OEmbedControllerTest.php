<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * The link the embed dialog's Link tab hands out: the commit's page, flagged as an embed, with the embed's signed query.
 */
function embedLink(Commit $commit, array $parameters = []): string
{
    $signed = URL::signedRoute('commit-embed.show', ['commit' => $commit->id, ...$parameters], absolute: false);
    $query = (string) parse_url($signed, PHP_URL_QUERY);

    return route('commit.show', ['profile' => $commit->author->handle, 'commit' => $commit->id]).'?embed&'.$query;
}

it('describes a signed commit link as a rich embed of its card', function (): void {
    $commit = Commit::factory()->for(User::factory()->state(['name' => 'Mira Okafor', 'handle' => 'mira']))->create();
    $link = embedLink($commit, ['replies' => 3, 'max_height' => 480]);

    $response = $this->getJson(route('oembed.show', ['url' => $link, 'format' => 'json']))
        ->assertOk()
        ->assertJson([
            'version' => '1.0',
            'type' => 'rich',
            'provider_name' => '/dev/null',
            'provider_url' => config('app.url'),
            'author_name' => 'Mira Okafor',
            'author_url' => route('user.show', 'mira'),
            'width' => 550,
            'height' => 525,
        ]);

    $html = $response->json('html');
    $src = html_entity_decode((string) str($html)->match('/src="([^"]+)"/'));

    expect($html)->toContain('<iframe')->toContain(url('embed.js'))->toContain('color-scheme:normal')
        ->and($src)->toStartWith(route('commit-embed.show', $commit));

    $this->get($src)->assertOk();
});

it('fits the width the consumer asks for', function (): void {
    $commit = Commit::factory()->create();

    $this->getJson(route('oembed.show', ['url' => embedLink($commit), 'maxwidth' => 400]))
        ->assertOk()
        ->assertJsonPath('width', 400);
});

it('refuses bare, tampered and foreign links', function (callable $link, int $status): void {
    $commit = Commit::factory()->create();

    $this->getJson(route('oembed.show', ['url' => $link($commit)]))->assertStatus($status);
})->with([
    'bare commit link' => [fn (Commit $commit): string => route('commit.show', ['profile' => $commit->author->handle, 'commit' => $commit->id]), 401],
    'tampered settings' => [fn (Commit $commit): string => str_replace('replies=1', 'replies=10', embedLink($commit, ['replies' => 1])), 401],
    'another site' => [fn (Commit $commit): string => 'https://example.com/@x/commits/'.$commit->id.'?embed', 404],
    'not a commit' => [fn (Commit $commit): string => url('/@'.$commit->author->handle), 404],
    'unknown commit' => [fn (Commit $commit): string => str_replace($commit->id, '01a1b7c7-0000-7000-8000-000000000000', embedLink($commit)), 404],
]);

it('refuses commits that cannot be embedded', function (): void {
    $commit = Commit::factory()->for(User::factory()->state(['allows_embeds' => false]))->create();

    $this->getJson(route('oembed.show', ['url' => embedLink($commit)]))->assertUnauthorized();
});

it('only speaks json', function (): void {
    $commit = Commit::factory()->create();

    $this->get(route('oembed.show', ['url' => embedLink($commit), 'format' => 'xml']))->assertStatus(501);
});

it('advertises the oembed endpoint on the page of a signed commit link only', function (): void {
    $commit = Commit::factory()->create();
    $link = embedLink($commit);

    $this->get($link)->assertSee(e(route('oembed.show', ['url' => $link, 'format' => 'json'])), false);
    $this->get(route('commit.show', ['profile' => $commit->author->handle, 'commit' => $commit->id]))->assertDontSee('application/json+oembed', false);
});
