<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;

it('shows an organization at the handle no user has, to guests too', function (): void {
    $laravel = Profile::factory()->organization()->create([
        'github_id' => '958072',
        'handle' => 'laravel',
        'name' => 'Laravel',
        'bio' => 'The PHP framework for web artisans.',
        'website' => 'https://laravel.com',
    ]);

    $this->get('/@laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/show')
            ->where('organization.id', $laravel->id)
            ->where('organization.handle', 'laravel')
            ->where('organization.name', 'Laravel')
            ->where('organization.bio', 'The PHP framework for web artisans.')
            ->where('organization.website', 'https://laravel.com')
            ->missing('organization.github_id')
            ->missing('organization.github_synced_at'));
});

it('shows guests the link preview of the organization', function (): void {
    $laravel = Profile::factory()->organization()->create([
        'handle' => 'laravel',
        'name' => 'Laravel',
        'bio' => 'The PHP framework for web artisans.',
    ]);

    $this->get('/@laravel')
        ->assertOk()
        ->assertSee('<meta property="og:type" content="profile">', false)
        ->assertSee('<meta property="og:title" content="Laravel (@laravel) on /dev/null">', false)
        ->assertSee('<meta property="og:description" content="The PHP framework for web artisans.">', false)
        ->assertSee('<meta property="og:url" content="'.route('user.show', 'laravel').'">', false)
        ->assertSee('<meta property="og:image" content="'.route('user-open-graph-image.show', ['profile' => 'laravel', 'v' => $laravel->openGraphImageVersion()]).'">', false)
        ->assertSee('<meta property="profile:username" content="laravel">', false)
        ->assertSee('application/ld+json', false);
});

it('keeps the default link preview of an organization for signed-in users', function (): void {
    Profile::factory()->organization()->create(['handle' => 'laravel']);

    $this->actingAs(User::factory()->create())
        ->get('/@laravel')
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.url('/og.png').'">', false)
        ->assertDontSee('og:url', false)
        ->assertDontSee('application/ld+json', false);
});

it('does not find handles that are neither a user nor an organization', function (): void {
    $this->get('/@nobody')->assertNotFound();
});

it('shares what every page shares, like the signed-in user', function (): void {
    $viewer = User::factory()->create();
    Profile::factory()->organization()->create(['handle' => 'laravel']);

    $this->actingAs($viewer)->get('/@laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/show')
            ->where('name', config('app.name'))
            ->where('auth.user.id', $viewer->id)
            ->has('auth.unreadMessagesCount'));
});

it('lists the commits and replies that mention the organization, newest first, to guests too', function (): void {
    $laravel = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $symfony = Profile::factory()->organization()->create(['handle' => 'symfony']);
    $mention = fn (Commit $commit, ?Profile $organization = null): Commit => tap($commit, fn () => resolve(CreateCommitTopics::class)->handle($commit, [($organization ?? $laravel)->handle => ($organization ?? $laravel)->id]));

    $older = $mention(Commit::factory()->create(['body' => 'Thanks @laravel', 'created_at' => now()->subHour()]));
    $parent = Commit::factory()->create(['body' => 'Which framework?', 'created_at' => now()->subMinutes(30)]);
    $reply = $mention(Commit::factory()->for($parent, 'parent')->create(['body' => '@laravel of course']));
    $mention(Commit::factory()->pending()->create(['body' => 'Pending @laravel']));
    $mention(Commit::factory()->create(['body' => 'Deleted @laravel']))->delete();
    $mention(Commit::factory()->create(['body' => 'Thanks @symfony']), $symfony);

    $this->get('/@laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/show')
            ->where('tab', 'mentions')
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $reply->id)
            ->where('commits.data.0.parent.id', $parent->id)
            ->where('commits.data.0.parent.user.id', $parent->user_id)
            ->where('commits.data.0.organization_mentions.laravel', $laravel->id)
            ->where('commits.data.1.id', $older->id));
});

it('lists the mentions as the viewer may see them, without their muted authors', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $laravel = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $mention = fn (Commit $commit): Commit => tap($commit, fn () => resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $laravel->id]));

    $pending = $mention(Commit::factory()->for($viewer)->pending()->create(['body' => 'Pending @laravel']));
    $hiddenParent = $mention(Commit::factory()->for(Commit::factory()->pending(), 'parent')->create(['body' => '@laravel', 'created_at' => now()->subMinute()]));
    $mention(Commit::factory()->for($muted)->create(['body' => 'Muted @laravel']));
    $mention(Commit::factory()->for($blocked)->create(['body' => 'Blocked @laravel']));

    $this->actingAs($viewer)->get('/@laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/show')
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $pending->id)
            ->where('commits.data.1.id', $hiddenParent->id)
            ->where('commits.data.1.parent', null));
});

it('pages through the mentions with the tab in the link to the next page', function (): void {
    $laravel = Profile::factory()->organization()->create(['handle' => 'laravel']);

    Commit::factory()->count(21)->sequence(fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)])->create(['body' => 'Thanks @laravel'])
        ->each(fn (Commit $commit) => resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $laravel->id]));

    $this->get('/@laravel?tab=mentions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'mentions')
            ->has('commits.data', 20)
            ->where('commits.next_page_url', fn (string $url): bool => str_contains($url, 'tab=mentions')));
});

it('keeps listing the commits that mentioned the organization before it was renamed', function (): void {
    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $commit = Commit::factory()->create(['body' => 'Thanks @laravel']);
    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => $organization->id]);

    $organization->forceFill(['handle' => 'laravel-php'])->save();

    $this->get('/@laravel-php')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/show')
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $commit->id)
            ->where('commits.data.0.organization_mentions.laravel', $organization->id));
});
