<?php

declare(strict_types=1);

use App\Enums\Section;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\User;

it('lists approved top-level commits of the section newest first', function (): void {
    $user = User::factory()->create();

    $older = Commit::factory()->inSection(Section::Hiring)->create(['body' => 'Older', 'created_at' => now()->subMinutes(2)]);
    $newer = Commit::factory()->inSection(Section::Hiring)->create(['body' => 'Newer', 'created_at' => now()->subMinute()]);
    Commit::factory()->create(['body' => 'Just a commit']);
    Commit::factory()->inSection(Section::Hiring)->pending()->create();
    Commit::factory()->inSection(Section::Hiring)->rejected()->create();
    Commit::factory()->inSection(Section::Hiring)->create()->delete();
    Commit::factory()->for($older, 'parent')->create(['body' => 'A reply']);

    $this->actingAs($user)
        ->get('/hiring')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('section/show')
            ->where('section.value', 'hiring')
            ->where('section.title', 'Hiring')
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $newer->id)
            ->where('commits.data.0.section', 'hiring')
            ->where('commits.data.0.user.id', $newer->user_id)
            ->where('commits.data.0.stars_count', 0)
            ->where('commits.data.1.id', $older->id));
});

it('shows the author their own pending commit, like the logs do', function (): void {
    $user = User::factory()->create();
    $pending = Commit::factory()->for($user)->inSection(Section::Hiring)->pending()->create();
    Commit::factory()->inSection(Section::Hiring)->pending()->create();

    $this->actingAs($user)
        ->get(route('section.show', Section::Hiring))
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $pending->id));
});

it('leaves out commits of muted and blocked authors', function (): void {
    $user = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $user->muting()->attach($muted);
    $user->blocking()->attach($blocked);

    Commit::factory()->for($muted)->inSection(Section::Hiring)->create();
    Commit::factory()->for($blocked)->inSection(Section::Hiring)->create();
    $visible = Commit::factory()->inSection(Section::Hiring)->create();

    $this->actingAs($user)
        ->get('/hiring')
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $visible->id));
});

it('pages through the commits with a cursor', function (): void {
    Commit::factory()->inSection(Section::Hiring)->count(25)->sequence(fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)])->create();

    $this->actingAs(User::factory()->create())
        ->get('/hiring')
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 20)
            ->whereNot('commits.next_cursor', null));
});

it('renders an empty page when nothing was posted to the section', function (): void {
    Commit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/hiring')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('commits.data', 0));
});

it('shows guests the commits with a generic preview that is not indexed', function (): void {
    Commit::factory()->inSection(Section::Hiring)->create(['body' => 'We are hiring a Rust engineer.']);

    $response = $this->get('/hiring')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('<meta property="og:title" content="Hiring on /dev/null">', false);

    expect(explode('</head>', (string) $response->getContent())[0])->not->toContain('Rust engineer');
});

it('lets guests page through the commits without a preview', function (): void {
    $commit = Commit::factory()->inSection(Section::Hiring)->create();

    $this->get('/hiring', ['X-Inertia' => 'true', 'X-Inertia-Version' => resolve(HandleInertiaRequests::class)->version(request())])
        ->assertOk()
        ->assertJsonPath('props.commits.data.0.id', $commit->id);
});

it('shares the sections with every page', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/hiring')
        ->assertInertia(fn ($page) => $page->where('sections', [['value' => 'hiring', 'title' => 'Hiring']]));
});

it('only answers to the sections that exist', function (): void {
    $this->actingAs(User::factory()->create())->get('/nowhere')->assertNotFound();
});
