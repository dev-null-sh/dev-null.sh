<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('renders own commits and commits of followed users newest first', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $stranger = User::factory()->create();

    $user->following()->attach($followed);

    Commit::factory()->for($user)->create(['body' => 'Mine', 'created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($followed)->create(['body' => 'Followed', 'created_at' => now()->subMinute()]);
    Commit::factory()->for($stranger)->create(['body' => 'Stranger']);

    $response = $this->actingAs($user)
        ->get('/log/following');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('log/show')
            ->where('log', 'following')
            ->has('commits.data', 2)
            ->where('commits.data.0.body', 'Followed')
            ->where('commits.data.0.user.id', $followed->id)
            ->where('commits.data.0.stars_count', 0)
            ->where('commits.data.0.starred', false)
            ->missing('commits.data.0.user.email')
            ->where('commits.data.1.body', 'Mine'));
});

it('renders each author with their effective lane', function (): void {
    $user = User::factory()->create();
    $chosen = User::factory()->create(['preferred_lane' => 5]);
    $automatic = User::factory()->create(['id' => 'edfd235d-6874-4a82-a1ba-4f40c5f40510']);

    Commit::factory()->for($chosen)->create(['created_at' => now()->subMinute()]);
    Commit::factory()->for($automatic)->create();

    $response = $this->actingAs($user)
        ->get('/log/everyone');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.user.lane', 2)
            ->where('commits.data.1.user.lane', 5)
            ->missing('commits.data.1.user.preferred_lane'));
});

it('renders commits of everyone newest first', function (): void {
    $user = User::factory()->create();

    Commit::factory()->create(['body' => 'Older', 'created_at' => now()->subMinute()]);
    Commit::factory()->create(['body' => 'Newer']);

    $response = $this->actingAs($user)
        ->get('/log/everyone');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('log/show')
            ->where('log', 'everyone')
            ->has('commits.data', 2)
            ->where('commits.data.0.body', 'Newer')
            ->where('commits.data.1.body', 'Older'));
});

it('paginates the log', function (Log $log): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->count(25)->sequence(
        fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();

    $response = $this->actingAs($user)
        ->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 20)
            ->whereNot('commits.next_cursor', null));
})->with([Log::Following, Log::Everyone]);

it('does not count the authenticated user relations when reloading only commits', function (): void {
    $user = User::factory()->has(Commit::factory())->create();

    $response = $this->actingAs($user)
        ->get('/log/everyone');

    $response->assertOk()
        ->assertInertia(function ($page): void {
            $page->where('auth.user.commits_count', 1);

            DB::enableQueryLog();

            $page->reloadOnly('commits', fn ($reload) => $reload->missing('auth'));
        });

    expect(collect(DB::getQueryLog())
        ->pluck('query')
        ->reject(fn (string $query): bool => str_starts_with($query, 'select "commits".*'))
        ->all())
        ->not->toBeEmpty()
        ->each->not->toContain('count(*)');
});

it('returns not found for unknown logs', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get('/log/unknown');

    $response->assertNotFound();
});

it('requires authentication', function (Log $log): void {
    $response = $this->get(route('log.show', $log));

    $response->assertRedirectToRoute('home');
})->with(Log::cases());

it('counts commits newer than a given commit', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach($followed);

    $seen = Commit::factory()->for($followed)->create(['created_at' => now()->subMinutes(5)]);
    Commit::factory()->for($followed)->count(2)->create();
    Commit::factory()->for($followed)->create(['created_at' => now()->subMinutes(10)]);
    Commit::factory()->create();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
            'X-Inertia-Partial-Component' => 'log/show',
            'X-Inertia-Partial-Data' => 'newCommitsCount',
        ])
        ->get('/log/following?since='.$seen->id);

    $response->assertOk()
        ->assertJsonPath('props.newCommitsCount', 2);
});

it('counts new commits since a commit that was deleted, without deleted ones', function (): void {
    $user = User::factory()->create();

    $seen = Commit::factory()->for($user)->create(['created_at' => now()->subMinutes(5)]);
    Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->create()->delete();
    $seen->delete();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
            'X-Inertia-Partial-Component' => 'log/show',
            'X-Inertia-Partial-Data' => 'newCommitsCount',
        ])
        ->get('/log/following?since='.$seen->id);

    $response->assertOk()
        ->assertJsonPath('props.newCommitsCount', 1);
});

it('does not count new commits on a regular visit', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get('/log/following');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->missing('newCommitsCount'));
});

it('suggests recent commits from other developers when following nobody', function (): void {
    $user = User::factory()->create();
    $author = User::factory()->create();

    Commit::factory()->for($author)->create(['body' => 'Older', 'created_at' => now()->subMinute()]);
    Commit::factory()->for($author)->create(['body' => 'Newer']);
    Commit::factory()->for(User::factory())->create(['created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->get('/log/following');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('suggestions', 2)
            ->where('suggestions.0.body', 'Newer')
            ->where('suggestions.0.user.id', $author->id)
            ->where('suggestions.1.user.id', fn (string $id): bool => $id !== $author->id && $id !== $user->id));
});

it('does not suggest commits when following someone', function (): void {
    $user = User::factory()->create();
    $user->following()->attach(User::factory()->create());
    Commit::factory()->create();

    $response = $this->actingAs($user)
        ->get('/log/following');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('suggestions', []));
});

it('hides pending commits of others from the log', function (Log $log): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();

    $user->following()->attach($followed);

    Commit::factory()->for($user)->pending()->create(['body' => 'Mine']);
    Commit::factory()->for($followed)->pending()->create(['body' => 'Followed']);

    $response = $this->actingAs($user)
        ->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.body', 'Mine')
            ->where('commits.data.0.status', 'pending'));
})->with([Log::Following, Log::Everyone]);

it('only suggests approved commits', function (): void {
    $user = User::factory()->create();

    Commit::factory()->pending()->create(['body' => 'Pending']);
    Commit::factory()->rejected()->create(['body' => 'Rejected']);
    Commit::factory()->create(['body' => 'Approved']);

    $response = $this->actingAs($user)
        ->get('/log/following');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('suggestions', 1)
            ->where('suggestions.0.body', 'Approved'));
});

it('only shows top-level commits with their reply counts', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create(['body' => 'Root']);
    Commit::factory()->for($parent, 'parent')->count(2)->create();
    Commit::factory()->for($parent, 'parent')->pending()->create();

    $response = $this->actingAs($user)
        ->get('/log/everyone');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.body', 'Root')
            ->where('commits.data.0.replies_count', 2));
});

it('only suggests top-level commits', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create(['body' => 'Root commit', 'created_at' => now()->subMinute()]);
    Commit::factory()->for($parent, 'parent')->create(['body' => 'A reply']);

    $response = $this->actingAs($user)
        ->get('/log/following');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('suggestions', 1)
            ->where('suggestions.0.body', 'Root commit'));
});

it('includes the link previews of commits', function (Log $log): void {
    $user = User::factory()->create();

    $linkPreview = LinkPreview::factory()->fetched()->create([
        'url' => 'https://laravel.com',
        'title' => 'Laravel',
        'description' => 'The PHP framework for web artisans.',
        'image_url' => 'https://laravel.com/og.png',
        'site_name' => 'Laravel',
    ]);

    Commit::factory()->for($user)->for($linkPreview)->create(['created_at' => now()->subMinute()]);
    Commit::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.link_preview', null)
            ->where('commits.data.1.link_preview', [
                'url' => 'https://laravel.com',
                'title' => 'Laravel',
                'description' => 'The PHP framework for web artisans.',
                'image_url' => 'https://laravel.com/og.png',
                'site_name' => 'Laravel',
            ]));
})->with([Log::Following, Log::Everyone]);

it('defers suggested users to follow', function (): void {
    $user = User::factory()->create();
    $suggested = User::factory()->has(Commit::factory())->create();

    $response = $this->actingAs($user)
        ->get('/log/everyone');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('showsWhoToFollow', true)
            ->missing('suggestedUsers')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('suggestedUsers', 1)
                ->where('suggestedUsers.0.id', $suggested->id)
                ->where('suggestedUsers.0.followed_by_following_count', 0)
                ->missing('suggestedUsers.0.email')));

    expect(collect($response->viewData('page')['deferredProps'] ?? [])->flatten())->toContain('suggestedUsers');
});

it('does not suggest users to follow once they are hidden', function (): void {
    $user = User::factory()->create();
    User::factory()->has(Commit::factory())->create();

    $response = $this->actingAs($user)
        ->withUnencryptedCookie('who_to_follow', 'false')
        ->get('/log/everyone');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('showsWhoToFollow', false)
            ->missing('suggestedUsers'));

    expect(collect($response->viewData('page')['deferredProps'] ?? [])->flatten())->not->toContain('suggestedUsers');
});

it('renders commits trending this week', function (): void {
    $user = User::factory()->create();

    Commit::factory()->create(['body' => 'Quiet']);
    $starred = Commit::factory()->create(['body' => 'Starred', 'created_at' => now()->subHour()]);
    $starred->stars()->attach(User::factory()->count(2)->create());
    $replied = Commit::factory()->create(['body' => 'Replied', 'created_at' => now()->subHours(2)]);
    Commit::factory()->for($replied, 'parent')->create();
    $lastWeek = Commit::factory()->create(['body' => 'Last week', 'created_at' => now()->subDays(8)]);
    $lastWeek->stars()->attach(User::factory()->count(10)->create());

    $response = $this->actingAs($user)
        ->get(route('log.show', Log::Trending));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('log/show')
            ->where('log', 'trending')
            ->has('commits.data', 2)
            ->where('commits.data.0.body', 'Starred')
            ->where('commits.data.0.stars_count', 2)
            ->where('commits.data.0.user.id', $starred->user_id)
            ->where('commits.data.1.body', 'Replied')
            ->where('commits.data.1.replies_count', 1)
            ->where('suggestions', []));
});

it('paginates trending commits without repeating any', function (): void {
    $user = User::factory()->create();
    $fan = User::factory()->create();

    Commit::factory()->count(25)->sequence(
        fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create()->each(fn (Commit $commit) => $commit->stars()->attach($fan));

    $firstPage = $this->actingAs($user)
        ->get(route('log.show', Log::Trending))
        ->viewData('page')['props']['commits'];

    $secondPage = $this->actingAs($user)
        ->get(route('log.show', [Log::Trending, 'cursor' => $firstPage['next_cursor']]))
        ->viewData('page')['props']['commits'];

    $ids = collect([...$firstPage['data'], ...$secondPage['data']])->pluck('id');

    expect($firstPage['data'])->toHaveCount(20)
        ->and($secondPage['data'])->toHaveCount(5)
        ->and($secondPage['next_cursor'])->toBeNull()
        ->and($ids->unique())->toHaveCount(25)
        ->and($ids->all())->toBe(Commit::query()->latest()->pluck('id')->all());
});

it('does not count new commits on the trending log', function (): void {
    $user = User::factory()->create();
    $fan = User::factory()->create();

    $seen = Commit::factory()->create(['created_at' => now()->subMinutes(5)]);
    $seen->stars()->attach($fan);
    Commit::factory()->create()->stars()->attach($fan);

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
            'X-Inertia-Partial-Component' => 'log/show',
            'X-Inertia-Partial-Data' => 'newCommitsCount',
        ])
        ->get(route('log.show', [Log::Trending, 'since' => $seen->id]));

    $response->assertOk()
        ->assertJsonPath('props.newCommitsCount', 0);
});

it('loads trending commits with the same number of queries however many there are', function (): void {
    $user = User::factory()->create();
    $fan = User::factory()->create();

    $queriesFor = function (int $count) use ($user, $fan): int {
        Commit::factory()->count($count)->create()->each(function (Commit $commit) use ($fan): void {
            $commit->stars()->attach($fan);
            Commit::factory()->for($commit, 'parent')->create();
        });

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get(route('log.show', Log::Trending))->assertOk();

        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    expect($queriesFor(1))->toBe($queriesFor(10));
});
