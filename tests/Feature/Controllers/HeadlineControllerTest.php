<?php

declare(strict_types=1);

use App\Contracts\Services\EmbeddingProvider;
use App\Contracts\Services\RelevanceProvider;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\Headline;
use App\Models\User;

it('requires sign in to read headline discussions', function (): void {
    $headline = Headline::factory()->create();

    $this->get(route('headline.show', $headline))->assertRedirectToRoute('home');
});

it('shows a fresh recap and only linked commits from the past week, newest first without semantic search', function (): void {
    $headline = Headline::factory()->create(['name' => 'Database indexing', 'summary' => 'Developers compare indexing strategies.', 'summary_created_at' => now()->subHour()]);
    $oldest = Commit::factory()->hasAttached($headline)->create(['created_at' => now()->subWeek()]);
    $newest = Commit::factory()->hasAttached($headline)->create(['created_at' => now()->subHour()]);
    $middle = Commit::factory()->hasAttached($headline)->create(['created_at' => now()->subDays(2)]);
    Commit::factory()->hasAttached($headline)->create(['created_at' => now()->subWeek()->subSecond()]);
    Commit::factory()->create(['body' => 'Database indexing', 'embedding' => embeddingVector(0)]);
    Commit::factory()->hasAttached(Headline::factory()->create())->create();
    Commit::factory()->pending()->hasAttached($headline)->create();
    Commit::factory()->rejected()->hasAttached($headline)->create();
    Commit::factory()->hasAttached($headline)->create(['deleted_at' => now()]);
    $this->mock(EmbeddingProvider::class)->shouldNotReceive('embed');
    $this->mock(RelevanceProvider::class)->shouldNotReceive('decideRelevance');

    $this->actingAs(User::factory()->create())->get(route('headline.show', $headline))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('headline/show')
        ->where('name', 'Database indexing')
        ->where('summary', 'Developers compare indexing strategies.')
        ->has('commits', 3)
        ->where('commits.0.id', $newest->id)
        ->where('commits.1.id', $middle->id)
        ->where('commits.2.id', $oldest->id))
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('omits unavailable or expired recaps without hiding the discussions', function (?string $summary, ?string $created): void {
    $headline = Headline::factory()->create(['summary' => $summary, 'summary_created_at' => $created === null ? null : now()->modify($created)]);
    $commit = Commit::factory()->hasAttached($headline)->create();

    $this->actingAs(User::factory()->create())->get(route('headline.show', $headline))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('summary', null)->has('commits', 1)->where('commits.0.id', $commit->id));
})->with([
    'no useful recap' => [null, null],
    'no timestamp' => ['An incomplete recap.', null],
    'exactly expired' => ['An old recap.', '-2 hours'],
]);

it('does not repeat muted or blocked authors through a shared recap', function (string $relationship, bool $isReply): void {
    $viewer = User::factory()->create();
    $hidden = User::factory()->create();
    $viewer->{$relationship}()->attach($hidden);
    $headline = Headline::factory()->create(['summary' => 'A recap with hidden contributions.', 'summary_created_at' => now()]);
    if ($isReply) {
        $root = Commit::factory()->hasAttached($headline)->create();
        Commit::factory()->for($hidden)->for($root, 'parent')->create();
    } else {
        Commit::factory()->for($hidden)->hasAttached($headline)->create(['embedding' => embeddingVector(0)]);
    }

    $visible = $isReply ? $root : Commit::factory()->hasAttached($headline)->create();

    $this->actingAs($viewer)->get(route('headline.show', $headline))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('summary', null)->has('commits', 1)->where('commits.0.id', $visible->id));
})->with(['muting', 'blocking'])->with(['root' => false, 'reply' => true]);

it('returns not found for an unknown headline', function (): void {
    $this->actingAs(User::factory()->create())->get('/headlines/00000000-0000-4000-8000-000000000000')->assertNotFound();
});

it('omits discussions for unrelated and recap-only partial reloads', function (string $props): void {
    $headline = Headline::factory()->create(['summary' => 'A fresh recap.', 'summary_created_at' => now()]);
    $this->mock(EmbeddingProvider::class)->shouldNotReceive('embed');

    $response = $this->actingAs(User::factory()->create())->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'headline/show',
        'X-Inertia-Partial-Data' => $props,
    ])->get(route('headline.show', $headline))->assertOk()
        ->assertJsonMissingPath('props.commits');

    if ($props === 'summary') {
        $response->assertJsonPath('props.summary', 'A fresh recap.');
    } else {
        $response->assertJsonMissingPath('props.summary');
    }
})->with(['auth.unreadNotificationsCount,auth.unreadMessagesCount', 'whoToFollow', 'summary']);

it('clears the displayed recap on the partial reload after deleting a linked source', function (): void {
    $user = User::factory()->create();
    $headline = Headline::factory()->create(['summary' => 'A recap of the source.', 'summary_created_at' => now()]);
    $source = Commit::factory()->for($user)->hasAttached($headline)->create(['embedding' => embeddingVector(0)]);
    $url = route('headline.show', $headline);

    $this->actingAs($user)->get($url)->assertInertia(fn ($page) => $page->where('summary', 'A recap of the source.'));
    $this->from($url)->delete(route('commit.destroy', $source))->assertRedirect($url);
    $this->mock(EmbeddingProvider::class)->shouldNotReceive('embed');

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'headline/show',
        'X-Inertia-Partial-Data' => 'summary',
    ])->get($url)->assertOk()->assertJsonPath('props.summary', null)->assertJsonMissingPath('props.commits');

    expect($headline->refresh()->summary)->toBeNull();
    $this->assertSoftDeleted($source);
});

it('does not charge headline browsing to the semantic search budget', function (): void {
    $headline = Headline::factory()->create();
    $this->actingAs(User::factory()->create());

    for ($attempt = 0; $attempt < 15; $attempt++) {
        $this->get(route('headline.show', $headline))->assertOk();
    }

    $this->get(route('headline.show', $headline))->assertOk();
    $this->getJson(route('commit-search.index', ['q' => 'indexing']))->assertOk();
});

it('allows headline pages and partial reloads after exhausting the semantic search budget', function (): void {
    $headline = Headline::factory()->create(['summary' => 'A fresh recap.', 'summary_created_at' => now()]);
    $this->actingAs(User::factory()->create());

    for ($attempt = 0; $attempt < 15; $attempt++) {
        $this->getJson(route('commit-search.index', ['q' => 'indexing']))->assertOk();
    }

    $this->getJson(route('commit-search.index', ['q' => 'indexing']))->assertTooManyRequests();
    $this->get(route('headline.show', $headline))->assertOk();
    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'headline/show',
    ]);
    $this->mock(EmbeddingProvider::class)->shouldNotReceive('embed');

    foreach (['summary', 'whoToFollow', 'auth.unreadNotificationsCount'] as $props) {
        $this->withHeader('X-Inertia-Partial-Data', $props)->get(route('headline.show', $headline))
            ->assertOk()->assertJsonMissingPath('props.commits');
    }

    $this->withHeader('X-Inertia-Partial-Data', 'summary')->get(route('headline.show', $headline))
        ->assertOk()->assertJsonPath('props.summary', 'A fresh recap.');
    $this->withHeader('X-Inertia-Partial-Component', 'search/index')->get(route('headline.show', $headline))->assertOk();
    $this->withHeader('X-Inertia-Partial-Component', 'headline/show');

    foreach (['', 'commits', 'summary,commits', 'commits.0'] as $props) {
        $this->withHeader('X-Inertia-Partial-Data', $props)->get(route('headline.show', $headline))->assertOk();
    }

    $this->withHeader('X-Inertia-Partial-Except', 'summary')->get(route('headline.show', $headline))->assertOk();
    $this->withHeader('X-Inertia-Partial-Data', '');
    $this->withHeader('X-Inertia-Partial-Except', 'commits')->get(route('headline.show', $headline))
        ->assertOk()->assertJsonPath('props.summary', 'A fresh recap.')->assertJsonMissingPath('props.commits');
});
