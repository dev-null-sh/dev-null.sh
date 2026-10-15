<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('renders a user profile', function (): void {
    $user = User::factory()->create(['username' => 'octocat', 'preferred_lane' => 4]);
    $viewer = User::factory()->create();

    $user->followers()->attach($viewer);
    $user->following()->attach(User::factory()->count(2)->create());

    Commit::factory()->for($user)->create(['created_at' => now()->subDay(), 'body' => 'Older']);
    Commit::factory()->for($user)->create(['body' => 'Newer']);
    Commit::factory()->create();

    $response = $this->actingAs($viewer)
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/show')
            ->where('user.id', $user->id)
            ->where('user.username', 'octocat')
            ->where('user.lane', 4)
            ->where('user.commits_count', 2)
            ->where('user.followers_count', 1)
            ->where('user.following_count', 2)
            ->missing('user.email')
            ->missing('user.github_id')
            ->where('isFollowing', true)
            ->has('commits.data', 2)
            ->where('commits.data.0.body', 'Newer')
            ->where('commits.data.0.user.username', 'octocat')
            ->where('commits.data.1.body', 'Older'));
});

it('does not count the user relations or activity when reloading only commits', function (): void {
    $user = User::factory()->has(Commit::factory())->create(['username' => 'octocat']);

    $response = $this->actingAs(User::factory()->create())
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(function ($page): void {
            $page->where('user.commits_count', 1);

            DB::enableQueryLog();

            $page->reloadOnly('commits', fn ($reload) => $reload
                ->missing('user')
                ->missing('isFollowing')
                ->missing('activity'));
        });

    expect(collect(DB::getQueryLog())
        ->pluck('query')
        ->reject(fn (string $query): bool => str_starts_with($query, 'select "commits".*'))
        ->all())
        ->not->toBeEmpty()
        ->each(fn ($query) => $query->not->toContain('count(*)')->not->toContain('exists'));
});

it('renders the user commit activity for the last year', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));

    $user = User::factory()->create(['username' => 'octocat']);

    Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->for($user)->create(['created_at' => now()->subDays(3)]);
    Commit::factory()->for($user)->create(['created_at' => now()->subYears(2)]);
    Commit::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activity.scale', 'year')
            ->has('activity.buckets', 369)
            ->where('activity.buckets.0', ['date' => '2025-10-12', 'count' => 0])
            ->where('activity.buckets.365', ['date' => '2026-10-12', 'count' => 1])
            ->where('activity.buckets.368', ['date' => '2026-10-15', 'count' => 2]));
});

it('renders the hourly commit activity of a user who only committed today', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));

    $user = User::factory()->create(['username' => 'octocat', 'created_at' => now()->subMonths(3)]);

    Commit::factory()->for($user)->count(2)->create();
    Commit::factory()->for($user)->create(['created_at' => now()->subHours(3)]);

    $response = $this->actingAs(User::factory()->create())
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activity.scale', 'day')
            ->has('activity.buckets', 24)
            ->where('activity.buckets.20', ['date' => '2026-10-15T09:00:00Z', 'count' => 1])
            ->where('activity.buckets.23', ['date' => '2026-10-15T12:00:00Z', 'count' => 2]));
});

it('renders whether the viewer is not following the user', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get(route('user.show', $user));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isFollowing', false)
            ->has('commits.data', 0));
});

it('returns not found for unknown usernames', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get('/@missing');

    $response->assertNotFound();
});

it('shows guests the landing page with a link preview of the profile', function (): void {
    $user = User::factory()->create([
        'username' => 'octocat',
        'name' => 'The Octocat',
        'bio' => 'I am the Octocat.',
    ]);

    Commit::factory()->for($user)->create(['body' => 'A commit only signed-in users may read']);

    $response = $this->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->missing('user')
            ->missing('commits')
            ->missing('activity'))
        ->assertSee('<title>The Octocat (@octocat) on /dev/null</title>', false)
        ->assertSee('<meta property="og:type" content="profile">', false)
        ->assertSee('<meta property="og:title" content="The Octocat (@octocat) on /dev/null">', false)
        ->assertSee('<meta property="og:description" content="I am the Octocat.">', false)
        ->assertSee('<meta property="og:url" content="'.route('user.show', $user).'">', false)
        ->assertSee('<meta property="og:image" content="'.route('user-open-graph-image.show', $user).'?v=', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertDontSee(url('/og.png'))
        ->assertDontSee('A commit only signed-in users may read');

    $this->assertGuest();
});

it('describes guests a profile without a bio', function (): void {
    User::factory()->create(['username' => 'octocat', 'name' => 'octocat', 'bio' => null]);

    $this->get('/@octocat')
        ->assertOk()
        ->assertSee('<meta property="og:title" content="@octocat on /dev/null">', false)
        ->assertSee('<meta property="og:description" content="Sign in with GitHub to read @octocat&#039;s commits on /dev/null.">', false);
});

it('escapes the profile in the link preview', function (): void {
    User::factory()->create([
        'username' => 'octocat',
        'name' => '"><script>alert(1)</script>',
        'bio' => '<img src=x onerror=alert(1)>',
    ]);

    $this->get('/@octocat')
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x', false)
        ->assertSee('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt; (@octocat) on /dev/null', false);
});

it('changes the link preview image version only when public profile data changes', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    $version = fn (): string => Str::betweenFirst($this->get('/@octocat')->getContent(), 'og.png?v=', '"');

    $initial = $version();

    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    expect($version())->toBe($initial);

    Commit::factory()->for($user)->create();

    expect($version())->not->toBe($initial);
});

it('returns guests to the profile after signing in', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    $this->get('/@octocat')->assertOk();

    expect(session('url.intended'))->toBe(route('user.show', $user));
});

it('returns not found for unknown usernames to guests', function (): void {
    $this->get('/@missing')->assertNotFound();
});

it('keeps the default link preview for signed-in users', function (): void {
    User::factory()->create(['username' => 'octocat', 'name' => 'The Octocat']);

    $this->actingAs(User::factory()->create())
        ->get('/@octocat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('user/show'))
        ->assertSee('<meta property="og:image" content="'.url('/og.png').'">', false)
        ->assertDontSee('og:url', false);
});

it('may delete user account', function (): void {
    $user = User::factory()->create();
    Commit::factory()->for($user)->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->delete(route('user.destroy'));

    $response->assertRedirectToRoute('home');

    expect($user->fresh())->toBeNull()
        ->and(Commit::query()->count())->toBe(0);

    $this->assertGuest();
});

it('hides pending and rejected commits from other users', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    Commit::factory()->for($user)->create(['body' => 'Approved']);
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    $response = $this->actingAs(User::factory()->create())
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.commits_count', 1)
            ->where('activity.buckets.23.count', 1)
            ->has('commits.data', 1)
            ->where('commits.data.0.body', 'Approved'));
});

it('shows own pending and rejected commits', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->pending()->create();
    Commit::factory()->for($user)->rejected()->create();

    $response = $this->actingAs($user)
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.commits_count', 3)
            ->where('activity.buckets.23.count', 3)
            ->has('commits.data', 3));
});

it('hides deleted commits from profiles', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);

    Commit::factory()->for($user)->create(['body' => 'Kept']);
    Commit::factory()->for($user)->create()->delete();

    $response = $this->actingAs($user)
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('user.commits_count', 1)
            ->where('activity.buckets.23.count', 1)
            ->has('commits.data', 1)
            ->where('commits.data.0.body', 'Kept'));
});

it('only shows top-level commits on profiles', function (): void {
    $user = User::factory()->create(['username' => 'octocat']);
    $parent = Commit::factory()->for($user)->create(['body' => 'Root']);
    Commit::factory()->for($user)->for($parent, 'parent')->create();

    $response = $this->actingAs(User::factory()->create())
        ->get('/@octocat');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.body', 'Root')
            ->where('commits.data.0.replies_count', 1));
});
