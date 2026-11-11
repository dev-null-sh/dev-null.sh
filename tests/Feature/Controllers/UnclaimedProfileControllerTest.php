<?php

declare(strict_types=1);

use App\Actions\CreateCommitTopics;
use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('shows an unclaimed developer to anyone with the link, but never where they are', function (): void {
    Profile::factory()->create([
        'handle' => 'octocat',
        'name' => 'The Octocat',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
        'bio' => 'I am the Octocat.',
        'location' => 'San Francisco',
        'website' => 'https://github.blog',
    ]);

    $this->get('/@octocat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('profile/unclaimed')
            ->where('profile', [
                'id' => Profile::query()->where('handle', 'octocat')->sole()->id,
                'handle' => 'octocat',
                'name' => 'The Octocat',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/583231',
                'bio' => 'I am the Octocat.',
            ]));
});

it('previews the page of an unclaimed developer for guests, kept out of search engines', function (): void {
    $profile = Profile::factory()->create(['handle' => 'octocat', 'name' => 'The Octocat', 'bio' => null]);

    $this->get('/@octocat')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('<meta property="og:title" content="The Octocat (@octocat) on /dev/null">', false)
        ->assertSee('<meta property="og:description" content="@octocat isn&#039;t on /dev/null yet.">', false)
        ->assertSee(e(route('user-open-graph-image.show', ['profile' => 'octocat', 'v' => $profile->openGraphImageVersion()])), false);

    $this->actingAs(User::factory()->create())
        ->get('/@octocat')
        ->assertOk()
        ->assertDontSee('The Octocat (@octocat) on /dev/null', false);
});

it('moves for good to the handle of the unclaimed developer in its own case', function (): void {
    Profile::factory()->create(['handle' => 'OctoCat']);

    $this->get('/@octocat?tab=mentions')->assertRedirect('/@OctoCat?tab=mentions')->assertStatus(301);
});

it('shows a member their own page, not the unclaimed one, once they signed in', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);

    $this->get('/@octocat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('user/show')->where('user.id', $user->id));
});

it('shows how many members wait to follow them, and members the ones they follow among them', function (): void {
    $profile = Profile::factory()->create(['handle' => 'octocat']);
    $member = User::factory()->create();
    [$followed, $other] = User::factory()->count(2)->create();
    $stranger = User::factory()->create();
    $member->following()->attach([$followed->id, $other->id]);
    DB::table('pending_follows')->insert(collect([$followed, $stranger, $member])->map(fn (User $user): array => ['user_id' => $user->id, 'github_id' => $profile->github_id, 'created_at' => now()])->all());

    $this->get('/@octocat')
        ->assertInertia(fn ($page) => $page
            ->where('waitingCount', 3)
            ->where('isWaiting', false)
            ->where('waitingYouFollow', []));

    $this->actingAs($member)
        ->get('/@octocat')
        ->assertInertia(fn ($page) => $page
            ->where('waitingCount', 3)
            ->where('isWaiting', true)
            ->where('waitingYouFollow', [$followed->only(['id', 'handle', 'name', 'avatar_url'])]));
});

it('opens on their own commits, which wait for them to sign up', function (): void {
    $profile = Profile::factory()->create(['handle' => 'octocat']);
    $commit = Commit::factory()->create(['body' => 'Thanks @octocat']);
    resolve(CreateCommitTopics::class)->handle($commit, [], ['octocat' => $profile->id]);

    $this->get('/@octocat')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('profile/unclaimed')
            ->where('tab', 'commits')
            ->has('commits.data', 0)
            ->where('commits.next_page_url', null));
});

it('lists the commits and replies that mention them, newest first, to guests too', function (): void {
    $profile = Profile::factory()->create(['handle' => 'octocat']);
    $other = Profile::factory()->create(['handle' => 'monalisa']);
    $mention = fn (Commit $commit, ?Profile $mentioned = null): Commit => tap($commit, fn () => resolve(CreateCommitTopics::class)->handle($commit, [], [($mentioned ?? $profile)->handle => ($mentioned ?? $profile)->id]));

    $older = $mention(Commit::factory()->create(['body' => 'Thanks @octocat', 'created_at' => now()->subHour()]));
    $parent = Commit::factory()->create(['body' => 'Who made this?', 'created_at' => now()->subMinutes(30)]);
    $reply = $mention(Commit::factory()->for($parent, 'parent')->create(['body' => '@octocat did']));
    $mention(Commit::factory()->pending()->create(['body' => 'Pending @octocat']));
    $mention(Commit::factory()->create(['body' => 'Deleted @octocat']))->delete();
    $mention(Commit::factory()->create(['body' => 'Thanks @monalisa']), $other);

    $this->get('/@octocat?tab=mentions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('profile/unclaimed')
            ->where('tab', 'mentions')
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $reply->id)
            ->where('commits.data.0.parent.id', $parent->id)
            ->where('commits.data.0.developer_mentions.octocat', $profile->id)
            ->where('commits.data.1.id', $older->id));
});

it('lists their mentions as the viewer may see them, without their muted authors', function (): void {
    $viewer = User::factory()->create();
    $muted = User::factory()->create();
    $blocked = User::factory()->create();
    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $profile = Profile::factory()->create(['handle' => 'octocat']);
    $mention = fn (Commit $commit): Commit => tap($commit, fn () => resolve(CreateCommitTopics::class)->handle($commit, [], ['octocat' => $profile->id]));

    $pending = $mention(Commit::factory()->for($viewer)->pending()->create(['body' => 'Pending @octocat']));
    $hiddenParent = $mention(Commit::factory()->for(Commit::factory()->pending(), 'parent')->create(['body' => '@octocat', 'created_at' => now()->subMinute()]));
    $mention(Commit::factory()->for($muted)->create(['body' => 'Muted @octocat']));
    $mention(Commit::factory()->for($blocked)->create(['body' => 'Blocked @octocat']));

    $this->actingAs($viewer)->get('/@octocat?tab=mentions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $pending->id)
            ->where('commits.data.1.id', $hiddenParent->id)
            ->where('commits.data.1.parent', null));
});

it('pages through their mentions with the tab in the link to the next page', function (): void {
    $profile = Profile::factory()->create(['handle' => 'octocat']);

    Commit::factory()->count(21)->sequence(fn ($sequence): array => ['created_at' => now()->subMinutes($sequence->index)])->create(['body' => 'Thanks @octocat'])
        ->each(fn (Commit $commit) => resolve(CreateCommitTopics::class)->handle($commit, [], ['octocat' => $profile->id]));

    $this->get('/@octocat?tab=mentions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 20)
            ->where('commits.next_page_url', fn (string $url): bool => str_contains($url, 'tab=mentions')));
});
