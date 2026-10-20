<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Enums\Section;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\User;
use App\Queries\SuggestedCommitsQuery;
use App\Queries\SuggestedUsersQuery;
use App\Queries\UserActivityQuery;
use App\Queries\UserStreakQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/*
 * Commits posted to a section are only ever listed on the section's page. Each test below is a place that would
 * otherwise list them, with a commit in a section next to one in the logs, and the section's text never allowed in.
 */

const LEAK = 'SECTION-ONLY-TEXT';

/**
 * @return list<string>
 */
function idsIn(TestResponse $response, string $prop): array
{
    $rows = $response->assertOk()->inertiaProps($prop);

    return array_values(array_column($rows, 'id'));
}

beforeEach(function (): void {
    $this->author = User::factory()->create(['username' => 'octocat']);
    $this->posted = Commit::factory()->for($this->author)->inSection(Section::Hiring)->create(['body' => LEAK]);
    $this->logged = Commit::factory()->for($this->author)->create(['body' => 'An ordinary commit']);
    $this->reply = Commit::factory()->for(User::factory()->create(['username' => 'replier']))->for($this->posted, 'parent')->create(['body' => 'Interested!']);
});

it('keeps them out of every log', function (string $log): void {
    $viewer = User::factory()->create();
    $viewer->following()->attach($this->author);

    expect(idsIn($this->actingAs($viewer)->get("/log/{$log}"), 'commits.data'))->toBe([$this->logged->id]);
})->with(['following', 'latest', 'trending']);

it('keeps them out of the logs of guests', function (string $log): void {
    expect(idsIn($this->get("/log/{$log}"), 'commits.data'))->toBe([$this->logged->id]);
})->with(['latest', 'trending']);

it('keeps them out of the count of new commits on the latest log', function (): void {
    $viewer = User::factory()->create();

    $since = Commit::factory()->create(['created_at' => now()->subHour()]);

    $this->actingAs($viewer)->get(route('log.show', [Log::Latest, 'since' => $since->id]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'newCommitsCount',
    ])->assertOk()->assertJsonPath('props.newCommitsCount', 1);
});

it('keeps them out of the profile and its counts', function (): void {
    $viewer = User::factory()->create();

    $response = $this->actingAs($viewer)->get('/@octocat');

    expect(idsIn($response, 'commits.data'))->toBe([$this->logged->id])
        ->and($response->inertiaProps('user.commits_count'))->toBe(1)
        ->and($response->inertiaProps('pinnedCommit'))->toBeNull();

    $this->get('/@octocat?tab=replies')
        ->assertInertia(fn ($page) => $page->where('commits.data', []));
});

it('keeps them out of the author’s own profile too', function (): void {
    $response = $this->actingAs($this->author)->get('/@octocat');

    expect(idsIn($response, 'commits.data'))->toBe([$this->logged->id])
        ->and($response->inertiaProps('user.commits_count'))->toBe(1);
});

it('hides them as the parent of a reply on the replies tab', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/@replier?tab=replies')
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.id', $this->reply->id)
            ->where('commits.data.0.parent', null));
});

it('does not count them as activity or streaks', function (): void {
    $activity = resolve(UserActivityQuery::class)->get($this->author, null);

    expect(array_sum(array_column($activity['buckets'], 'count')))->toBe(1)
        ->and(resolve(UserStreakQuery::class)->get($this->author, null))->toBe(['longest' => 1, 'current' => 1]);

    $this->posted->forceFill(['created_at' => now()->subDays(3)])->save();
    $this->logged->delete();

    $activity = resolve(UserActivityQuery::class)->get($this->author, $this->author);

    expect(array_sum(array_column($activity['buckets'], 'count')))->toBe(0)
        ->and(resolve(UserStreakQuery::class)->get($this->author, $this->author))->toBe(['longest' => 0, 'current' => 0]);
});

it('draws no cell for them in the activity graph', function (): void {
    $with = $this->get('/@octocat/graph.svg')->assertOk()->getContent();

    $this->posted->forceFill(['section' => null])->save();
    $without = $this->get('/@octocat/graph.svg')->assertOk()->getContent();

    expect($with)->not->toBe($without);
    $this->posted->forceFill(['section' => Section::Hiring])->save();
    expect($this->get('/@octocat/graph.svg')->getContent())->toBe($with);
});

it('gives them no experience', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/@octocat')
        ->assertInertia(fn ($page) => $page->where('achievements.xp_breakdown.commits.count', 1));
});

it('keeps them out of the Atom feed', function (): void {
    $feed = $this->get('/@octocat/feed.atom')->assertOk()->getContent();

    expect($feed)->toContain('An ordinary commit')
        ->not->toContain(LEAK)
        ->not->toContain($this->posted->id);
});

it('keeps them and their replies out of the public API', function (): void {
    $this->getJson('/api/v1/users/octocat/commits')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$this->logged->id]);

    $this->getJson('/api/v1/users/replier/commits?replies=1')
        ->assertOk()
        ->assertJsonPath('data', []);

    $this->getJson("/api/v1/commits/{$this->posted->id}")->assertNotFound();
    $this->getJson("/api/v1/commits/{$this->reply->id}")->assertNotFound();
    $this->getJson("/api/v1/commits/{$this->logged->id}")->assertOk();

    $this->getJson('/api/v1/users/octocat')->assertOk()->assertJsonPath('data.commits_count', 1);
});

it('keeps them out of the sitemaps', function (): void {
    $this->get(route('commit-sitemap.show', 1))
        ->assertOk()
        ->assertSee($this->logged->id)
        ->assertDontSee($this->posted->id)
        ->assertDontSee($this->reply->id);

    $this->logged->delete();

    $this->get(route('user-sitemap.show', 1))
        ->assertOk()
        ->assertDontSee('octocat')
        ->assertSee('replier');
});

it('keeps them out of link previews', function (): void {
    $this->get(route('commit.show', [$this->author, $this->posted]))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('<meta property="og:title" content="Hiring on /dev/null">', false);

    $this->get(route('commit.show', [$this->reply->user, $this->reply]))
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Hiring on /dev/null">', false);

    foreach ([$this->posted, $this->reply] as $commit) {
        $head = explode('</head>', (string) $this->get(route('commit.show', [$commit->user, $commit]))->getContent())[0];

        expect($head)->not->toContain(LEAK)->not->toContain('Interested!');
    }

    $this->get(route('commit.show', [$this->author, $this->logged]))
        ->assertSee('og:title', false)
        ->assertDontSee('Hiring on /dev/null');
});

it('draws no link preview image for them', function (): void {
    Http::fake(['*' => Http::response('', 500)]);

    $this->get(route('commit-open-graph-image.show', [$this->author, $this->posted]))->assertNotFound();
    $this->get(route('commit-open-graph-image.show', [$this->reply->user, $this->reply]))->assertNotFound();
    $this->get(route('commit-open-graph-image.show', [$this->author, $this->logged]))->assertOk();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), LEAK));
});

it('keeps them out of the profile link preview', function (): void {
    $before = $this->author->fresh()->loadPublicCounts()->openGraphImageVersion();

    $this->posted->forceFill(['section' => null])->save();

    expect($this->author->fresh()->loadPublicCounts()->openGraphImageVersion())->not->toBe($before)
        ->and($this->author->fresh()->loadPublicCounts()->commits_count)->toBe(2);
});

it('does not suggest them to read', function (): void {
    $viewer = User::factory()->create();
    $this->posted->forceFill(['created_at' => now()->addMinute()])->save();

    expect(resolve(SuggestedCommitsQuery::class)->get($viewer)->pluck('id')->all())->toBe([$this->logged->id]);
});

it('does not suggest people who only posted to a section', function (): void {
    $viewer = User::factory()->create();
    $onlySection = User::factory()->create(['username' => 'only_section']);
    Commit::factory()->for($onlySection)->inSection(Section::Hiring)->create();

    expect(resolve(SuggestedUsersQuery::class)->get($viewer)->pluck('username')->all())
        ->toContain('octocat')
        ->not->toContain('only_section');
});

it('does not find them by searching', function (): void {
    config(['services.openai.key' => 'sk-test']);
    fakeSearch();

    $this->posted->forceFill(['embedding' => embeddingVector(0)])->save();
    $this->logged->forceFill(['embedding' => embeddingVector(1)])->save();

    $this->getJson('/search/commits?q=feature')
        ->assertOk()
        ->assertJsonPath('commits.*.id', [$this->logged->id]);
});

it('still lists them where they belong', function (): void {
    $viewer = User::factory()->create();

    expect(idsIn($this->actingAs($viewer)->get('/hiring'), 'commits.data'))->toBe([$this->posted->id]);
    $this->get(route('commit.show', [$this->author, $this->posted]))->assertOk()->assertInertia(fn ($page) => $page
        ->component('commit/show')
        ->where('commit.id', $this->posted->id));
});
