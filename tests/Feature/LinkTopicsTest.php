<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\LinkPreview;
use App\Models\Organization;
use App\Models\User;

it('tells a card who else talks about its link once two people do', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh']);
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create(['link_preview_id' => $link->id, 'created_at' => now()->subWeek()]);
    $show = fn () => $this->get(route('commit.show', [$author, $commit]));

    $show()->assertInertia(fn ($page) => $page->where('commit.link_topic', null));

    Commit::factory()->for($author)->create(['link_preview_id' => $link->id]);

    $show()->assertInertia(fn ($page) => $page->where('commit.link_topic', null));

    $others = User::factory()->count(4)->create();
    $others->each(fn (User $other, int $index) => Commit::factory()->for($other)->create(['link_preview_id' => $link->id, 'created_at' => now()->subMinutes($index)]));
    Commit::factory()->for($others[0])->create(['link_preview_id' => $link->id]);
    Commit::factory()->pending()->create(['link_preview_id' => $link->id]);

    $show()->assertInertia(fn ($page) => $page
        ->where('commit.link_topic.url', url('link/dev-null.sh'))
        ->where('commit.link_topic.others', 6)
        ->where('commit.link_topic.authors', fn ($authors): bool => collect($authors)->pluck('id')->all() === [$others[0]->id, $others[1]->id, $others[2]->id]));
});

it('counts only the commits the viewer may see about a card link', function (): void {
    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->blocking()->attach($blocked);
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh']);
    $commit = Commit::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->for($blocked)->create(['link_preview_id' => $link->id]);

    $this->actingAs($viewer)->get(route('commit.show', [$commit->author, $commit]))
        ->assertInertia(fn ($page) => $page->where('commit.link_topic', null));
});

it('counts the people readers see, so an organization is one person whoever posts as it', function (): void {
    $organization = Organization::factory()->create();
    [$alice, $bob] = User::factory()->count(2)->create()->all();
    $organization->members()->attach([$alice->id => ['role' => OrganizationRole::Admin], $bob->id => ['role' => OrganizationRole::Member]]);
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh']);
    $commit = Commit::factory()->for($alice)->create(['link_preview_id' => $link->id, 'author_id' => $organization->id]);
    Commit::factory()->for($bob)->create(['link_preview_id' => $link->id, 'author_id' => $organization->id]);
    $show = fn () => $this->get(route('commit.show', [$commit->author, $commit]));

    $show()->assertInertia(fn ($page) => $page->where('commit.link_topic', null));
    $this->get(url('link/dev-null.sh'))->assertInertia(fn ($page) => $page->where('stats', ['commits' => 2, 'people' => 1]));

    Commit::factory()->for($alice)->create(['link_preview_id' => $link->id]);

    $show()->assertInertia(fn ($page) => $page->where('commit.link_topic.authors', fn ($authors): bool => collect($authors)->pluck('id')->all() === [$alice->id]));
});

it('leaves muted people out of who else talks about a link', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $viewer->muting()->attach($muted);
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh']);
    $commit = Commit::factory()->create(['link_preview_id' => $link->id]);
    $other = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subDay()]);
    Commit::factory()->for($muted)->create(['link_preview_id' => $link->id]);

    $this->actingAs($viewer)->get(route('commit.show', [$commit->author, $commit]))->assertInertia(fn ($page) => $page
        ->where('commit.link_topic.authors', fn ($authors): bool => collect($authors)->pluck('id')->all() === [$other->author_id])
        ->loadDeferredProps(fn ($reload) => $reload->where('linkTopic.commits', fn ($commits): bool => collect($commits)->pluck('id')->all() === [$other->id])));
});

it('leaves the commits of muted guilds out of who else talks about a link', function (): void {
    config(['app.admins' => ['admin']]);
    $viewer = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create();
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh']);
    $commit = Commit::factory()->create(['link_preview_id' => $link->id]);
    $other = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subDay()]);
    Commit::factory()->for($guild)->create(['link_preview_id' => $link->id]);
    $this->actingAs($viewer)->put(route('guild-mute.update', $guild), ['muted' => true])->assertRedirect();

    $this->get(route('commit.show', [$commit->author, $commit]))->assertInertia(fn ($page) => $page
        ->where('commit.link_topic.authors', fn ($authors): bool => collect($authors)->pluck('id')->all() === [$other->author_id])
        ->loadDeferredProps(fn ($reload) => $reload->where('linkTopic.commits', fn ($commits): bool => collect($commits)->pluck('id')->all() === [$other->id])));
});

it('never makes a topic of an http link', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'http://dev-null.sh']);
    $commit = Commit::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->create(['link_preview_id' => $link->id]);

    $this->get(route('commit.show', [$commit->author, $commit]))
        ->assertInertia(fn ($page) => $page->where('commit.link_topic', null));
});

it('tells a quoted commit card who else talks about its link', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh']);
    $quoted = Commit::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->create(['link_preview_id' => $link->id]);
    $quote = Commit::factory()->create(['quoted_commit_id' => $quoted->id]);

    $this->get(route('commit.show', [$quote->author, $quote]))
        ->assertInertia(fn ($page) => $page->where('commit.quoted_commit.link_topic.others', 1));
});

it('shows the most starred commits about the link from outside the thread on a commit page', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh', 'title' => 'dev/null', 'icon_url' => 'https://dev-null.sh/icon.png']);
    $author = User::factory()->create();
    $root = Commit::factory()->for($author)->create(['link_preview_id' => $link->id]);
    $reply = Commit::factory()->for($author)->create(['link_preview_id' => $link->id, 'parent_id' => $root->id, 'root_id' => $root->id]);
    $favourite = Commit::factory()->create(['link_preview_id' => $link->id, 'created_at' => now()->subDay()]);
    $favourite->stars()->attach(User::factory()->create());
    $others = Commit::factory()->count(3)->sequence(fn ($sequence): array => ['created_at' => now()->subHours($sequence->index + 1)])->create(['link_preview_id' => $link->id]);

    $this->get(route('commit.show', [$author, $reply]))->assertInertia(fn ($page) => $page
        ->missing('linkTopic')
        ->loadDeferredProps(fn ($reload) => $reload
            ->where('linkTopic.url', 'https://dev-null.sh')
            ->where('linkTopic.page_url', url('link/dev-null.sh'))
            ->where('linkTopic.title', 'dev/null')
            ->where('linkTopic.icon_url', 'https://dev-null.sh/icon.png')
            ->where('linkTopic.count', 6)
            ->where('linkTopic.commits', fn ($commits): bool => collect($commits)->pluck('id')->all() === [$favourite->id, $others[0]->id, $others[1]->id])));
});

it('shows no link section when only the thread talks about the link', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh']);
    $root = Commit::factory()->create(['link_preview_id' => $link->id]);
    Commit::factory()->create(['link_preview_id' => $link->id, 'parent_id' => $root->id, 'root_id' => $root->id]);

    $this->get(route('commit.show', [$root->author, $root]))->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($reload) => $reload->where('linkTopic', null)));
});

it('shows no link section until two people talk about the link', function (): void {
    $link = LinkPreview::factory()->fetched()->create(['url' => 'https://dev-null.sh']);
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create(['link_preview_id' => $link->id]);
    Commit::factory()->for($author)->create(['link_preview_id' => $link->id]);

    $this->get(route('commit.show', [$author, $commit]))->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($reload) => $reload->where('linkTopic', null)));
});

it('lists the links two people talk about in a sitemap of their own', function (): void {
    $talked = LinkPreview::factory()->create(['url' => 'https://dev-null.sh/about/']);
    Commit::factory()->create(['link_preview_id' => $talked->id, 'created_at' => '2026-10-15 08:00:00']);
    Commit::factory()->create(['link_preview_id' => $talked->id, 'created_at' => '2026-10-16 08:00:00']);
    $alone = LinkPreview::factory()->create(['url' => 'https://example.com']);
    Commit::factory()->count(2)->for(User::factory()->create())->create(['link_preview_id' => $alone->id]);
    $http = LinkPreview::factory()->create(['url' => 'http://example.org']);
    Commit::factory()->count(2)->create(['link_preview_id' => $http->id]);
    $pending = LinkPreview::factory()->create(['url' => 'https://example.net']);
    Commit::factory()->create(['link_preview_id' => $pending->id]);
    Commit::factory()->pending()->create(['link_preview_id' => $pending->id]);

    $this->get(route('sitemap.index'))->assertSee('<loc>'.route('link-sitemap.index').'</loc>', false);

    $response = $this->get(route('link-sitemap.index'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.url('link/dev-null.sh/about%2F').'</loc>', false)
        ->assertSee('<lastmod>2026-10-16T08:00:00+00:00</lastmod>', false);

    expect(mb_substr_count($response->getContent(), '<url>'))->toBe(1);
});

it('leaves the links sitemap out of the index while no link is talked about', function (): void {
    $this->get(route('sitemap.index'))->assertDontSee(route('link-sitemap.index'), false);
});
