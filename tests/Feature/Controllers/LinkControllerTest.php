<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;

it('keeps the link page to signed-in users', function (): void {
    $this->get(route('link.index'))->assertRedirectToRoute('home');
});

it('ranks the links of the last 30 days by how many people used them as their card', function (): void {
    $popular = LinkPreview::factory()->fetched()->create();
    $chatty = LinkPreview::factory()->fetched()->create();
    $old = LinkPreview::factory()->fetched()->create();
    Commit::factory()->count(3)->create(['link_preview_id' => $popular->id]);
    $author = User::factory()->create();
    Commit::factory()->count(4)->for($author)->create(['link_preview_id' => $chatty->id]);
    Commit::factory()->count(5)->create(['link_preview_id' => $old->id, 'created_at' => now()->subDays(31)]);
    Commit::factory()->pending()->count(5)->create(['link_preview_id' => $chatty->id]);
    Commit::factory()->rejected()->count(5)->create(['link_preview_id' => $chatty->id]);
    Commit::factory()->count(5)->create(['link_preview_id' => $chatty->id])->each->delete();

    $this->actingAs(User::factory()->create())->get(route('link.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('links/index')
        ->where('url', null)
        ->has('topLinks', 2)
        ->where('topLinks.0.url', $popular->url)
        ->where('topLinks.0.people', 3)
        ->where('topLinks.0.commits', 3)
        ->where('topLinks.1.url', $chatty->url)
        ->where('topLinks.1.people', 1)
        ->where('topLinks.1.commits', 4));
});

it('shows only the 10 most shared links', function (): void {
    LinkPreview::factory()->count(11)->create()->each(fn (LinkPreview $link) => Commit::factory()->create(['link_preview_id' => $link->id]));

    $this->actingAs(User::factory()->create())->get(route('link.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('topLinks', 10));
});

it('lists the commits whose card is the link, newest first', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://wiki.php.net/rfc/pipe-operator']);
    $first = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subDay()]);
    $reply = Commit::factory()->create(['link_preview_id' => $link->id, 'parent_id' => $first->id]);
    Commit::factory()->create(['link_preview_id' => LinkPreview::factory()->create()->id]);
    Commit::factory()->create(['body' => 'Not the card: https://wiki.php.net/rfc/pipe-operator']);

    $this->actingAs(User::factory()->create())
        ->get(route('link.index', ['url' => 'https://wiki.php.net/rfc/pipe-operator']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('links/index')
            ->where('url', 'https://wiki.php.net/rfc/pipe-operator')
            ->where('link.url', 'https://wiki.php.net/rfc/pipe-operator')
            ->where('stats', ['commits' => 2, 'people' => 2])
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $reply->id)
            ->where('commits.data.1.id', $first->id));
});

it('nests the 3 most starred commits of the last 6 hours under each link, then its newest', function (): void {
    $link = LinkPreview::factory()->fetched()->create();
    $trending = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subDay()]);
    $oldFavourite = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subHours(3)]);
    $newest = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subHour()]);
    $newer = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subHours(2)]);
    $trending->stars()->attach(User::factory()->count(2)->create(), ['created_at' => now()->subHour()]);
    $oldFavourite->stars()->attach(User::factory()->count(5)->create(), ['created_at' => now()->subHours(7)]);

    $this->actingAs(User::factory()->create())->get(route('link.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('topLinks.0.stars_last_6h', 2)
        ->where('topLinks.0.commits', 4)
        ->has('topLinks.0.top_commits', 3)
        ->where('topLinks.0.top_commits.0.id', $trending->id)
        ->where('topLinks.0.top_commits.1.id', $newest->id)
        ->where('topLinks.0.top_commits.2.id', $newer->id));
});

it('counts every star of the approved commits of each link, however old', function (): void {
    $link = LinkPreview::factory()->fetched()->create();
    Commit::factory()->create(['link_preview_id' => $link->id])->stars()->attach(User::factory()->count(2)->create(), ['created_at' => now()->subWeek()]);
    Commit::factory()->create(['link_preview_id' => $link->id])->stars()->attach(User::factory()->create(), ['created_at' => now()]);
    Commit::factory()->pending()->create(['link_preview_id' => $link->id])->stars()->attach(User::factory()->create(), ['created_at' => now()]);

    $this->actingAs(User::factory()->create())->get(route('link.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('topLinks.0.stars', 3)
        ->where('topLinks.0.stars_last_6h', 1));
});

it('lists the links whose commits got stars in the last 6 hours first', function (): void {
    $shared = LinkPreview::factory()->fetched()->create();
    Commit::factory()->count(3)->create(['link_preview_id' => $shared->id]);
    $starred = LinkPreview::factory()->fetched()->create();
    Commit::factory()->create(['link_preview_id' => $starred->id])->stars()->attach(User::factory()->create(), ['created_at' => now()]);

    $this->actingAs(User::factory()->create())->get(route('link.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('topLinks.0.url', $starred->url)
        ->where('topLinks.1.url', $shared->url));
});

it('hides the commits the viewer may not see from the list of links', function (): void {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    $link = LinkPreview::factory()->fetched()->create();
    $visible = Commit::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->for($blocked)->create(['link_preview_id' => $link->id]);

    $this->actingAs($viewer)->get(route('link.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('topLinks.0.top_commits', 1)
        ->where('topLinks.0.top_commits.0.id', $visible->id));
});

it('hides the commits the viewer may not see from the link page', function (): void {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    $link = LinkPreview::factory()->create();
    $visible = Commit::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->for($blocked)->create(['link_preview_id' => $link->id]);
    Commit::factory()->pending()->create(['link_preview_id' => $link->id]);
    $own = Commit::factory()->pending()->for($viewer)->create(['link_preview_id' => $link->id]);

    $this->actingAs($viewer)->get(route('link.index', ['url' => $link->url]))->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data', 2)
        ->where('commits.data', fn ($commits): bool => collect($commits)->pluck('id')->sort()->values()->all() === collect([$visible->id, $own->id])->sort()->values()->all()));
});

it('finds the link of a bare domain like its card does', function (): void {
    $link = LinkPreview::factory()->create(['url' => 'https://dev-null.sh']);
    Commit::factory()->create(['link_preview_id' => $link->id]);

    $this->actingAs(User::factory()->create())->get(route('link.index', ['url' => ' dev-null.sh ']))->assertOk()->assertInertia(fn ($page) => $page
        ->where('url', 'https://dev-null.sh')
        ->has('commits.data', 1));
});

it('shows no commits for a link nobody used as their card', function (): void {
    $this->actingAs(User::factory()->create())->get(route('link.index', ['url' => 'https://example.com/nothing']))->assertOk()->assertInertia(fn ($page) => $page
        ->where('link', null)
        ->where('stats', ['commits' => 0, 'people' => 0])
        ->where('commits', null));
});
