<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('hands members the signed links of the card they chose', function (): void {
    $commit = Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-embed-link.show', ['commit' => $commit, 'thread' => 1, 'replies' => 5, 'depth' => 'all', 'theme' => 'dark', 'max_height' => 'none']))
        ->assertOk();

    $card = $response->json('card');
    $link = $response->json('link');

    expect($card)->toStartWith(route('commit-embed.show', $commit).'?')
        ->toContain('depth=all')->toContain('max_height=none')->toContain('replies=5')->toContain('theme=dark')->toContain('thread=1')
        ->and($link)->toStartWith(route('commit.show', ['profile' => $commit->author->handle, 'commit' => $commit]).'?embed&')
        ->and(parse_url($link, PHP_URL_QUERY))->toBe('embed&'.parse_url($card, PHP_URL_QUERY));

    $this->get($card)->assertOk();
    $this->getJson(route('oembed.show', ['url' => $link]))->assertOk();
});

it('hands the code to paste, highlighted like code in commits, for HTML, Markdown and a link', function (): void {
    $commit = Commit::factory()->for(User::factory()->state(['handle' => 'mira']))->create();

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-embed-link.show', ['commit' => $commit, 'max_height' => 480]))
        ->assertOk();

    $card = $response->json('card');
    $hash = mb_substr(str_replace('-', '', $commit->id), -7);

    expect($response->json('snippets.html.code'))->toBe(implode("\n", [
        '<iframe',
        '  src="'.$card.'"',
        '  title="Commit '.$hash.' on /dev/null"',
        '  width="550"',
        '  height="525"',
        '  loading="lazy"',
        '  style="border:0;max-width:100%;color-scheme:normal"',
        '></iframe>',
        '<script async src="'.url('embed.js').'"></script>',
    ]))
        ->and($response->json('snippets.html.block.type'))->toBe('block')
        ->and($response->json('snippets.html.block.language'))->toBe('html')
        ->and($response->json('snippets.html.block.html'))->toContain('iframe')
        ->and($response->json('snippets.markdown.code'))->toBe(
            '[![Commit '.$hash.' by @mira on /dev/null]('.route('commit-open-graph-image.show', ['profile' => 'mira', 'commit' => $commit]).')]('.route('commit.show', ['profile' => 'mira', 'commit' => $commit]).')',
        )
        ->and($response->json('snippets.markdown.block.language'))->toBe('markdown')
        ->and($response->json('snippets.link.code'))->toBe($response->json('link'));
});

it('signs only the settings that differ from the defaults, in their allowed values', function (): void {
    $commit = Commit::factory()->create();

    $card = $this->actingAs(User::factory()->create())
        ->getJson(route('commit-embed-link.show', ['commit' => $commit, 'replies' => 7, 'theme' => 'auto', 'max_height' => 640, 'extra' => 'x']))
        ->json('card');

    expect(array_keys(Illuminate\Support\Arr::except(Illuminate\Support\Str::of($card)->after('?')->explode('&')->mapWithKeys(fn (string $part): array => [explode('=', $part)[0] => true])->all(), [])))
        ->toBe(['signature']);
});

it('keeps embed links from guests', function (): void {
    $commit = Commit::factory()->create();

    $this->getJson(route('commit-embed-link.show', $commit))->assertUnauthorized();
});

it('refuses commits that cannot be embedded, as if they did not exist', function (string $state): void {
    $commit = match ($state) {
        'pending' => Commit::factory()->pending()->create(),
        'opted out' => Commit::factory()->for(User::factory()->state(['allows_embeds' => false]))->create(),
    };

    $this->actingAs(User::factory()->create())
        ->getJson(route('commit-embed-link.show', $commit))
        ->assertNotFound();
})->with(['pending', 'opted out']);

it('refuses embed links to someone the author blocks, as if the commit did not exist', function (): void {
    $author = User::factory()->create();
    $blocked = User::factory()->create();
    $author->blocking()->attach($blocked);
    $commit = Commit::factory()->for($author)->create();

    $this->actingAs($blocked)
        ->getJson(route('commit-embed-link.show', $commit))
        ->assertNotFound();
});

it('tells the web whether each commit can be embedded', function (): void {
    $commit = Commit::factory()->create();
    $optedOut = Commit::factory()->for(User::factory()->state(['allows_embeds' => false]))->create();

    $this->get(route('commit.show', ['profile' => $commit->author->handle, 'commit' => $commit]))
        ->assertInertia(fn ($page) => $page->where('commit.embeddable', true));
    $this->get(route('commit.show', ['profile' => $optedOut->author->handle, 'commit' => $optedOut]))
        ->assertInertia(fn ($page) => $page->where('commit.embeddable', false));
});
