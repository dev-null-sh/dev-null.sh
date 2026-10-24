<?php

declare(strict_types=1);

use App\Actions\UpdateCommitHotScores;
use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\DetectCommitLanguage;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\LinkPreview;
use App\Models\User;
use App\Services\Features;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

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
        ->get('/log/trending');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.user.lane', 2)
            ->where('commits.data.1.user.lane', 5)
            ->missing('commits.data.1.user.preferred_lane'));
});

it('renders commits of everyone newest first', function (): void {
    $user = User::factory()->create();

    $starred = Commit::factory()->create(['body' => 'Older', 'created_at' => now()->subMinute()]);
    $starred->stars()->attach(User::factory()->create());
    resolve(UpdateCommitHotScores::class)->handle([$starred->id]);
    Commit::factory()->create(['body' => 'Newer']);

    $response = $this->actingAs($user)
        ->get('/log/latest');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('log/show')
            ->where('log', 'latest')
            ->where('isRanked', false)
            ->has('commits.data', 2)
            ->where('commits.data.0.body', 'Newer')
            ->where('commits.data.1.body', 'Older'));
});

it('renders commits of everyone ranked by popularity', function (): void {
    $user = User::factory()->create();

    $starred = Commit::factory()->create(['body' => 'Starred', 'created_at' => now()->subHour()]);
    $starred->stars()->attach(User::factory()->create());
    resolve(UpdateCommitHotScores::class)->handle([$starred->id]);
    Commit::factory()->create(['body' => 'Newer']);

    $response = $this->actingAs($user)
        ->get('/log/trending');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('log/show')
            ->where('log', 'trending')
            ->where('isRanked', true)
            ->has('commits.data', 2)
            ->where('commits.data.0.body', 'Starred')
            ->where('commits.data.1.body', 'Newer'));
});

it('redirects the former everyone log to the trending log', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get('/log/everyone');

    $response->assertRedirect('/log/trending')
        ->assertStatus(301);
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
})->with(Log::cases());

it('draws the trending log anew on each visit', function (): void {
    config(['trending.temperature' => 0.5]);
    $user = User::factory()->create();
    Commit::factory()->count(10)->create(['created_at' => now()]);

    $draw = fn (): array => array_column($this->actingAs($user)->get('/log/trending')->inertiaProps('commits.data'), 'id');

    expect($draw())->not->toBe($draw());
});

it('keeps the draw of the trending log while it scrolls', function (): void {
    config(['trending.temperature' => 0.5]);
    $user = User::factory()->create();
    $commits = Commit::factory()->count(25)->sequence(
        fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();

    $first = $this->actingAs($user)->get('/log/trending');
    $second = $this->actingAs($user)->get(route('log.show', [Log::Trending, 'cursor' => $first->inertiaProps('commits.next_cursor')]));

    $ids = array_column([...$first->inertiaProps('commits.data'), ...$second->inertiaProps('commits.data')], 'id');

    expect($ids)->toEqualCanonicalizing($commits->modelKeys())
        ->and($second->inertiaProps('commits.next_cursor'))->toBeNull();
});

it('does not count the authenticated user relations when reloading only commits', function (): void {
    $user = User::factory()->has(Commit::factory())->create();

    $response = $this->actingAs($user)
        ->get('/log/trending');

    $response->assertOk()
        ->assertInertia(function ($page): void {
            $page->where('auth.user.commits_count', 1);

            DB::enableQueryLog();

            $page->reloadOnly('commits', fn ($reload) => $reload->missing('auth'));
        });

    expect(collect(DB::getQueryLog())
        ->pluck('query')
        ->reject(fn (string $query): bool => str_starts_with($query, 'select "commits"."id", "commits"."user_id"') || str_starts_with($query, 'select * from (select "commits"."id"'))
        ->all())
        ->not->toBeEmpty()
        ->each->not->toContain('count(*)');
});

it('remembers the last visited log', function (Log $log): void {
    $user = User::factory()->create(['log' => $log === Log::Following ? Log::Trending : Log::Following]);

    $response = $this->actingAs($user)
        ->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.log', $log->value));

    expect($user->refresh()->log)->toBe($log);
})->with(Log::cases());

it('does not update the user when the log is unchanged', function (): void {
    $user = User::factory()->create(['log' => Log::Trending]);
    $updatedAt = $user->updated_at;

    $this->travel(1)->minute();

    $this->actingAs($user)
        ->get(route('log.show', Log::Trending))
        ->assertOk();

    expect($user->refresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('returns not found for unknown logs', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get('/log/unknown');

    $response->assertNotFound();
});

it('lets guests read the public logs', function (Log $log): void {
    $author = User::factory()->create();
    $commit = Commit::factory()->for($author)->create();

    $response = $this->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('log/show')
            ->where('log', $log->value)
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $commit->id));
})->with([Log::Latest, Log::Trending]);

it('sends guests to sign in with GitHub for the following log', function (): void {
    $this->get(route('log.show', Log::Following))
        ->assertRedirect(route('login'));

    $this->get(route('log.show', Log::Following), ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request())])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('login'));
});

it('does not count newer commits on the trending log', function (?bool $signedIn): void {
    $older = Commit::factory()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->create();

    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get(route('log.show', [Log::Trending, 'since' => $older->id]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'newCommitsCount',
    ])->assertOk()->assertJsonPath('props.newCommitsCount', 0);
})->with(['guest' => false, 'signed in' => true]);

it('counts newer commits on the latest log', function (): void {
    $user = User::factory()->create();
    $older = Commit::factory()->create(['created_at' => now()->subMinute()]);
    Commit::factory()->create();

    $this->actingAs($user)->get(route('log.show', [Log::Latest, 'since' => $older->id]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'newCommitsCount',
    ])->assertOk()->assertJsonPath('props.newCommitsCount', 1);
});

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
    $followed = User::factory()->create();
    $user->following()->attach($followed);

    $seen = Commit::factory()->for($followed)->create(['created_at' => now()->subMinutes(5)]);
    Commit::factory()->for($followed)->create();
    Commit::factory()->for($followed)->create()->delete();
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

it('counts commits created in the same second after a given commit', function (): void {
    $user = User::factory()->create();
    $followed = User::factory()->create();
    $user->following()->attach($followed);
    $createdAt = now()->startOfSecond();

    Commit::factory()->for($followed)->create(['id' => '01a14074-2549-72dc-8b4d-cb2ab04034c7', 'created_at' => $createdAt]);
    $seen = Commit::factory()->for($followed)->create(['id' => '01a14074-2549-72dc-8b4d-cb2ab04034c8', 'created_at' => $createdAt]);
    Commit::factory()->for($followed)->create(['id' => '01a14074-2549-72dc-8b4d-cb2ab04034c9', 'created_at' => $createdAt]);

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

it('does not count their own new commits, committed from another device', function (Log $log): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $user->following()->attach($other);

    $seen = Commit::factory()->for($other)->create(['created_at' => now()->subMinutes(5)]);
    Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->for($other)->create();

    $this->actingAs($user)->get(route('log.show', [$log, 'since' => $seen->id]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'newCommitsCount',
    ])->assertOk()->assertJsonPath('props.newCommitsCount', 1);
})->with([Log::Following, Log::Latest]);

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
})->with(Log::cases());

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
        ->get('/log/trending');

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
                'is_pending' => false,
                'embed' => null,
            ]));
})->with(Log::cases());

it('includes the quoted commits with their authors', function (Log $log): void {
    $user = User::factory()->create();
    $quoted = Commit::factory()->for(User::factory()->state(['username' => 'octocat']))->create(['body' => 'The quoted commit', 'created_at' => now()->subDay()]);
    $hidden = Commit::factory()->create(['body' => 'A rejected quoted commit']);
    $deleted = Commit::factory()->create(['body' => 'A deleted quoted commit']);
    Commit::factory()->for($user)->for($quoted, 'quotedCommit')->create(['created_at' => now()->subMinutes(3)]);
    Commit::factory()->for($user)->for($hidden, 'quotedCommit')->create(['created_at' => now()->subMinutes(2)]);
    Commit::factory()->for($user)->for($deleted, 'quotedCommit')->create(['created_at' => now()->subMinute()]);
    $hidden->update(['status' => CommitStatus::Rejected]);
    $deleted->delete();

    $response = $this->actingAs($user)
        ->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.quoted_commit.id', $deleted->id)
            ->missing('commits.data.0.quoted_commit.body')
            ->where('commits.data.1.quoted_commit_id', $hidden->id)
            ->where('commits.data.1.quoted_commit', null)
            ->where('commits.data.2.quoted_commit.body', 'The quoted commit')
            ->where('commits.data.2.quoted_commit.user.username', 'octocat'))
        ->assertDontSee('A rejected quoted commit')
        ->assertDontSee('A deleted quoted commit');
})->with(Log::cases());

it('includes the images and link previews of quoted commits', function (Log $log): void {
    $user = User::factory()->create();
    $linkPreview = LinkPreview::factory()->fetched()->create(['title' => 'Laravel']);
    $quoted = Commit::factory()->for($linkPreview)->create(['created_at' => now()->subHour()]);
    CommitImage::factory()->for($quoted)->create(['width' => 1600, 'height' => 900]);
    Commit::factory()->for($user)->for($quoted, 'quotedCommit')->create();

    $response = $this->actingAs($user)
        ->get(route('log.show', $log));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data.0.quoted_commit.link_preview.title', 'Laravel')
            ->has('commits.data.0.quoted_commit.images', 1)
            ->where('commits.data.0.quoted_commit.images.0.width', 1600));
})->with(Log::cases());

it('loads the quoted commits of a log without a query per commit', function (Log $log): void {
    Queue::fake([DetectCommitLanguage::class]);
    resolve(Features::class)->all();
    $user = User::factory()->create(['log' => $log, 'last_seen_at' => today()]);

    $queries = function () use ($user, $log): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get(route('log.show', $log))->assertOk();

        return count(DB::getQueryLog());
    };

    Commit::factory()->for($user)->for(Commit::factory(), 'quotedCommit')->create();

    $expected = $queries();

    Commit::factory()->for($user)->for(Commit::factory(), 'quotedCommit')->count(5)->create();

    expect($queries())->toBe($expected);
})->with(Log::cases());

it('defers suggested users to follow', function (): void {
    $user = User::factory()->create();
    $suggested = User::factory()->has(Commit::factory())->create();
    $suggested->following()->attach($user);

    $response = $this->actingAs($user)
        ->get('/log/trending');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('showsWhoToFollow', true)
            ->missing('suggestedUsers')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('suggestedUsers', 1)
                ->where('suggestedUsers.0.id', $suggested->id)
                ->where('suggestedUsers.0.followed_by_following_count', 0)
                ->where('suggestedUsers.0.follows_you', true)
                ->where('suggestedUsers.0.bio', $suggested->bio)
                ->missing('suggestedUsers.0.email')));

    expect(collect($response->viewData('page')['deferredProps'] ?? [])->flatten())->toContain('suggestedUsers')
        ->and($response->viewData('page')['mergeProps'] ?? [])->toContain('suggestedUsers')
        ->and($response->viewData('page')['matchPropsOn'] ?? [])->toContain('suggestedUsers.id');
});

it('keeps the suggested users the client already loaded when navigating', function (): void {
    $user = User::factory()->create();
    User::factory()->has(Commit::factory())->create();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
            'X-Inertia-Except-Once-Props' => 'suggestedUsers',
        ])
        ->get('/log/trending');

    $response->assertOk()
        ->assertJsonMissingPath('props.suggestedUsers')
        ->assertJsonPath('onceProps.suggestedUsers.prop', 'suggestedUsers');

    expect(collect($response->json('deferredProps') ?? [])->flatten())->not->toContain('suggestedUsers');
});

it('does not suggest users to follow once they are hidden', function (): void {
    $user = User::factory()->create();
    User::factory()->has(Commit::factory())->create();

    $response = $this->actingAs($user)
        ->withUnencryptedCookie('who_to_follow', 'false')
        ->get('/log/trending');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('showsWhoToFollow', false)
            ->missing('suggestedUsers'));

    expect(collect($response->viewData('page')['deferredProps'] ?? [])->flatten())->not->toContain('suggestedUsers');
});

it('leaves the commit just committed to its rank on the ranked log', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $user = User::factory()->create(['username' => 'nunomaduro']);
    Commit::factory()->count(20)->create(['hot_score' => 1e12]);

    $response = $this->actingAs($user)
        ->from('/log/trending')
        ->followingRedirects()
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('commits.data', fn ($commits): bool => collect($commits)->doesntContain('body', 'Initial commit'))
            ->missing('latestCommits'));
});

it('puts the commit just committed on top of the latest log', function (): void {
    config(['app.admins' => ['nunomaduro']]);

    $user = User::factory()->create(['username' => 'nunomaduro']);
    Commit::factory()->count(20)->create(['created_at' => now()->subHour()]);

    $response = $this->actingAs($user)
        ->from('/log/latest')
        ->followingRedirects()
        ->post(route('commit.store'), ['body' => 'Initial commit']);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('commits.data.0.body', 'Initial commit'));
});

it('points feed commits to the rest of their author thread instead of previewing it', function (): void {
    $viewer = User::factory()->create();
    $thread = Commit::factory()->create();
    Commit::factory()->for($thread, 'parent')->create(['body' => 'Someone else', 'created_at' => now()->subMinutes(2)]);
    $next = Commit::factory()->for($thread, 'parent')->for($thread->user)->create(['body' => 'Part two', 'created_at' => now()->subMinute()]);

    $this->actingAs($viewer)->get(route('log.show', 'latest'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('commits.data.0.id', $thread->id)
        ->where('commits.data.0.continuation_id', $next->id)
        ->where('commits.data.0.replies_count', 1)
        ->has('commits.data.0.reply_preview.data', 1)
        ->where('commits.data.0.reply_preview.data.0.body', 'Someone else')
        ->where('commits.data.0.reply_preview.next_cursor', null));
});

it('preloads the newest replies per feed commit with a cursor to the older replies', function (): void {
    $viewer = User::factory()->create();
    $parents = Commit::factory()->for($viewer)->count(2)->create();
    foreach ($parents as $parent) {
        Commit::factory()->for($parent, 'parent')->create(['body' => 'Oldest reply', 'created_at' => now()->subMinutes(3)]);
        Commit::factory()->for($parent, 'parent')->create(['body' => 'Older reply', 'created_at' => now()->subMinutes(2)]);
        Commit::factory()->for($parent, 'quotedCommit')->create(['body' => 'Latest quote', 'created_at' => now()->subMinute()]);
        Commit::factory()->for($parent, 'parent')->pending()->create();
    }

    $response = $this->actingAs($viewer)
        ->withUnencryptedCookie('replies_per_page', '1')
        ->get(route('log.show', 'latest'));
    $response->assertOk()->assertInertia(function ($page): void {
        foreach ([0, 1] as $index) {
            $page->has("commits.data.{$index}.reply_preview.data", 1)
                ->where("commits.data.{$index}.reply_preview.data.0.body", 'Latest quote');
        }
    });
    foreach (array_slice($response->inertiaProps('commits.data'), 0, 2) as $commit) {
        $this->getJson(route('commit-reply.index', [
            $commit['id'], 'newest' => 1, 'cursor' => $commit['reply_preview']['next_cursor'],
        ]))->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'Older reply')
            ->assertJsonPath('data.1.body', 'Oldest reply')
            ->assertJsonPath('next_cursor', null);
    }
});

it('preloads as many of the newest replies as the viewer shows at a time', function (?string $cookie, int $shown): void {
    $viewer = User::factory()->create();
    $parent = Commit::factory()->for($viewer)->create();
    foreach (range(12, 1) as $minutes) {
        Commit::factory()->for($parent, 'parent')->create(['body' => "Reply {$minutes}", 'created_at' => now()->subMinutes($minutes)]);
    }

    $request = $cookie === null ? $this : $this->withUnencryptedCookie('replies_per_page', $cookie);

    $request->actingAs($viewer)->get(route('log.show', 'latest'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('commits.data.0.reply_preview.data', fn ($replies): bool => collect($replies)->pluck('body')->all() === array_map(
            fn (int $minutes): string => "Reply {$minutes}",
            range(1, $shown),
        ))
        ->where('commits.data.0.reply_preview.next_cursor', fn (?string $cursor): bool => ($cursor !== null) === ($shown < 12)));
})->with([
    'by default' => [null, 3],
    'when picked' => ['10', 10],
    'all of them' => ['25', 12],
]);

it('retains the loaded Following range when a follow request supplies its boundary', function (): void {
    $viewer = User::factory()->create();
    $loaded = Commit::factory()->for($viewer)->count(30)->sequence(
        fn (Sequence $sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();

    $this->actingAs($viewer)->get(route('log.show', Log::Following), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'commits',
        'X-Inertia-Reset' => 'commits',
        'X-Log-Through' => $loaded[24]->id,
    ])->assertOk()
        ->assertJsonCount(25, 'props.commits.data')
        ->assertJsonPath('props.commits.data.24.id', $loaded[24]->id)
        ->assertJsonPath('scrollProps.commits.reset', true);
});

it('retains the loaded Latest range when new commits arrive on top of it', function (): void {
    $viewer = User::factory()->create();
    $loaded = Commit::factory()->for($viewer)->count(30)->sequence(
        fn (Sequence $sequence): array => ['created_at' => now()->subHour()->subMinutes($sequence->index)],
    )->create();
    $arrived = Commit::factory()->count(3)->sequence(
        fn (Sequence $sequence): array => ['created_at' => now()->subMinutes($sequence->index)],
    )->create();

    $this->actingAs($viewer)->get(route('log.show', Log::Latest), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'commits',
        'X-Inertia-Reset' => 'commits',
        'X-Log-Through' => $loaded[24]->id,
    ])->assertOk()
        ->assertJsonCount(28, 'props.commits.data')
        ->assertJsonPath('props.commits.data.0.id', $arrived[0]->id)
        ->assertJsonPath('props.commits.data.27.id', $loaded[24]->id);
});

it('does not hold a ranked log to a loaded range', function (): void {
    $viewer = User::factory()->create();
    $loaded = Commit::factory()->for($viewer)->count(30)->create();

    $this->actingAs($viewer)->get(route('log.show', Log::Trending), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'commits',
        'X-Log-Through' => $loaded[24]->id,
    ])->assertOk()->assertJsonCount(20, 'props.commits.data');
});

it('ignores a missing or inaccessible range boundary', function (string $boundary): void {
    $viewer = User::factory()->create();
    $loaded = Commit::factory()->for($viewer)->count(25)->create();
    $hidden = Commit::factory()->pending()->create();
    $id = $boundary === 'missing' ? (string) Str::uuid() : $hidden->id;

    $this->actingAs($viewer)->get(route('log.show', Log::Following), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'commits',
        'X-Log-Through' => $id,
    ])->assertOk()->assertJsonCount(20, 'props.commits.data');
})->with(['missing', 'inaccessible']);

it('rejects an invalid range boundary', function (): void {
    $this->actingAs(User::factory()->create())
        ->withHeader('X-Log-Through', 'invalid')
        ->getJson(route('log.show', Log::Following))
        ->assertUnprocessable()->assertJsonValidationErrors('through');
});

it('polls only the count while loaded commits provide their authors', function (): void {
    $viewer = User::factory()->create();
    $older = Commit::factory()->create(['created_at' => now()->subMinute()]);
    $author = User::factory()->create();
    Commit::factory()->count(2)->for($author)->create();
    Commit::factory()->for($viewer)->create();

    $this->actingAs($viewer)->get(route('log.show', [Log::Latest, 'since' => $older->id]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'newCommitsCount',
    ])->assertOk()
        ->assertJsonPath('props.newCommitsCount', 2)
        ->assertJsonMissingPath('props.newCommitAuthors');
});

it('resets the pending count when reloading without a since cursor', function (): void {
    $this->actingAs(User::factory()->create())->get(route('log.show', Log::Latest), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'newCommitsCount',
    ])->assertOk()
        ->assertJsonPath('props.newCommitsCount', 0)
        ->assertJsonMissingPath('props.newCommitAuthors');
});

it('reconciles pending arrivals even when no newer commits exist', function (Log $log): void {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $viewer->following()->attach($author);
    $pending = Commit::factory()->for($author)->count(4)->create();
    $pending[0]->delete();
    $response = $this->actingAs($viewer)->get(route('log.show', [
        $log, 'since' => $pending[3]->id, 'pending' => $pending->modelKeys(),
    ]), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) resolve(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'log/show',
        'X-Inertia-Partial-Data' => 'newCommitsCount,removedCommitIds',
    ]);
    $response->assertOk()->assertJsonPath('props.newCommitsCount', 0)
        ->assertJsonPath('props.removedCommitIds', [$pending[0]->id])
        ->assertJsonMissingPath('props.commits');
})->with([Log::Following, Log::Latest]);

it('rejects malformed pending commit identifiers', function (array $pending, string $field): void {
    $this->actingAs(User::factory()->create())->getJson(route('log.show', [Log::Latest, ...$pending]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['pending' => 'bad'], 'pending'],
    [['pending' => ['bad']], 'pending.0'],
]);
