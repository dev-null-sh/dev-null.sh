<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Enums\OrganizationRole;
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
            ->missing('organization.github_synced_at')
            ->where('lane', null));
});

it('shows the organization in the lane its admins chose', function (array $attributes, int|string $lane): void {
    Profile::factory()->organization()->create(['handle' => 'laravel', ...$attributes]);

    $this->get('/@laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('lane', $lane)
            ->missing('organization.preferred_lane')
            ->missing('organization.lane_color'));
})->with([
    'from the palette' => [['preferred_lane' => 3], 3],
    'custom' => [['lane_color' => '#1a2b3c'], '#1a2b3c'],
]);

it('shows the admins of the organization but not its other members, first admin first, and where to claim it', function (): void {
    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $first = User::factory()->create();
    $second = User::factory()->create();
    $organization->admins()->attach([$second->id => ['created_at' => now()], $first->id => ['created_at' => now()->subDay()]]);
    $organization->members()->attach(User::factory()->create()->id, ['role' => OrganizationRole::Member, 'created_at' => now()->subWeek()]);

    $this->get('/@laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('claimUrl', route('organization-admin.create', $organization))
            ->where('settingsUrl', null)
            ->where('admins.0.id', $first->id)
            ->where('admins.0.handle', $first->profile->handle)
            ->where('admins.1.id', $second->id)
            ->missing('admins.0.pivot')
            ->has('admins', 2));
});

it('links its members to the settings of the organization, admins to its profile', function (): void {
    $organization = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $organization->admins()->attach($admin->id);
    $organization->members()->attach($member->id, ['role' => OrganizationRole::Member]);

    $this->actingAs($admin)->get('/@laravel')
        ->assertInertia(fn ($page) => $page->where('settingsUrl', route('organization-profile.edit', $organization)));

    $this->actingAs($member)->get('/@laravel')
        ->assertInertia(fn ($page) => $page->where('settingsUrl', route('organization-member.index', $organization)));

    $this->actingAs(User::factory()->create())->get('/@laravel')
        ->assertInertia(fn ($page) => $page->where('settingsUrl', null));
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

it('moves an organization for good to the handle in its own case, keeping the query string', function (): void {
    Profile::factory()->organization()->create(['handle' => 'Laravel']);

    $this->get('/@laravel?tab=mentions')
        ->assertMovedPermanently()
        ->assertRedirect('/@Laravel?tab=mentions')
        ->assertHeader('Cache-Control', 'max-age=86400, private');

    $this->get('/@Laravel')->assertOk();
});

it('keeps the query string out of the handle it moves an organization to', function (): void {
    Profile::factory()->organization()->create(['handle' => 'Laravel']);

    $this->get('/@laravel?user=evil')->assertRedirect('/@Laravel?user=evil');
    $this->get('/@laravel?user[]=a&user[]=b')->assertRedirect('/@Laravel?user[]=a&user[]=b');
    $this->get('/@laravel?tag=one&tag=two')->assertRedirect('/@Laravel?tag=one&tag=two');
});

it('opens each organization at its own case when a stale one holds the handle in another case', function (): void {
    $lower = Profile::factory()->organization()->create(['handle' => 'laravel']);
    $mixed = Profile::factory()->organization()->create(['handle' => 'Laravel']);

    $this->get('/@laravel')->assertOk()->assertInertia(fn ($page) => $page->where('organization.id', $lower->id));
    $this->get('/@Laravel')->assertOk()->assertInertia(fn ($page) => $page->where('organization.id', $mixed->id));

    $location = (string) $this->get('/@LARAVEL')->assertMovedPermanently()->headers->get('Location');

    expect($location)->toBeIn([url('/@laravel'), url('/@Laravel')]);

    $this->get($location)->assertOk();
});
