<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\PendingFollow;
use App\Models\PendingMention;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

function fakeUnclaimedAccount(string $login = 'ondrejmirtes', int $id = 104888, ?string $name = 'Ondřej Mirtes'): void
{
    Http::fake([
        "api.github.com/users/{$login}" => Http::response([
            'id' => $id,
            'login' => $login,
            'type' => 'User',
            'name' => $name,
            'avatar_url' => "https://avatars.githubusercontent.com/u/{$id}?v=4",
        ]),
    ]);
}

function mentionOf(string $username, ?Commit $commit = null): Commit
{
    $commit ??= Commit::factory()->create(['body' => "Thanks @{$username}!"]);

    PendingMention::factory()->for($commit)->create(['username' => mb_strtolower($username)]);

    return $commit;
}

it('renders the profile of a mentioned developer who is not on devnull yet', function (): void {
    fakeUnclaimedAccount();

    $older = mentionOf('ondrejmirtes', Commit::factory()->create(['created_at' => now()->subDay(), 'body' => 'Older @ondrejmirtes']));
    $newer = mentionOf('ondrejmirtes', Commit::factory()->create(['body' => 'Newer @ondrejmirtes']));
    mentionOf('someoneelse');

    $first = PendingFollow::factory()->create(['github_id' => '104888']);
    $second = PendingFollow::factory()->create(['github_id' => '104888']);
    PendingFollow::factory()->create();

    $this->get('/@ondrejmirtes')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('Ondřej Mirtes (@ondrejmirtes) on /dev/null', false)
        ->assertSee('@ondrejmirtes is not on /dev/null yet. 2 developers are waiting to follow.', false)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('user/unclaimed')
            ->where('account', [
                'username' => 'ondrejmirtes',
                'name' => 'Ondřej Mirtes',
                'avatar_url' => 'https://avatars.githubusercontent.com/u/104888?v=4',
            ])
            ->where('waiting.count', 2)
            ->has('waiting.users', 2)
            ->where('waiting.users.0.id', $second->user_id)
            ->where('waiting.users.1.id', $first->user_id)
            ->missing('waiting.users.0.email')
            ->missing('waiting.users.0.github_id')
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $newer->id)
            ->where('commits.data.1.id', $older->id));
});

it('finds the profile whatever the case of the username', function (): void {
    fakeUnclaimedAccount('OndrejMirtes');

    mentionOf('ondrejmirtes');

    $this->get('/@OndrejMirtes')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('user/unclaimed')
            ->where('account.username', 'OndrejMirtes')
            ->has('commits.data', 1));
});

it('lists the replies that mention the developer with their parent', function (): void {
    fakeUnclaimedAccount();

    $parent = Commit::factory()->create();
    $reply = mentionOf('ondrejmirtes', Commit::factory()->for($parent, 'parent')->create(['body' => 'cc @ondrejmirtes']));

    $this->get('/@ondrejmirtes')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('commits.data.0.id', $reply->id)
            ->where('commits.data.0.parent.id', $parent->id));
});

it('shows the mentions as the viewer may see them, without a link preview', function (): void {
    fakeUnclaimedAccount();

    $viewer = User::factory()->create();
    $pending = mentionOf('ondrejmirtes', Commit::factory()->for($viewer)->pending()->create(['body' => 'Mine @ondrejmirtes']));
    mentionOf('ondrejmirtes', Commit::factory()->create(['created_at' => now()->subMinute()]));
    mentionOf('ondrejmirtes', Commit::factory()->pending()->create());

    $this->actingAs($viewer)
        ->get('/@ondrejmirtes')
        ->assertOk()
        ->assertDontSee('<meta name="robots"', false)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('user/unclaimed')
            ->where('waiting', ['count' => 0, 'users' => []])
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $pending->id));
});

it('describes a profile only mentioned, or awaited by one developer, in its link preview', function (int $waiting, string $description): void {
    fakeUnclaimedAccount(name: null);

    mentionOf('ondrejmirtes');
    PendingFollow::factory()->count($waiting)->create(['github_id' => '104888']);

    $this->get('/@ondrejmirtes')
        ->assertOk()
        ->assertSee('<meta property="og:title" content="@ondrejmirtes on /dev/null">', false)
        ->assertSee(e($description), false);
})->with([
    [0, '@ondrejmirtes is not on /dev/null yet, but developers here are mentioning them.'],
    [1, '@ondrejmirtes is not on /dev/null yet. 1 developer is waiting to follow.'],
]);

it('is not found until a commit anyone may see mentions the username', function (): void {
    fakeUnclaimedAccount();

    mentionOf('ondrejmirtes', Commit::factory()->pending()->create());
    mentionOf('ondrejmirtes', Commit::factory()->trashed()->create());

    $this->get('/@ondrejmirtes')->assertNotFound();
    $this->get('/@nobody')->assertNotFound();

    Http::assertNothingSent();
});

it('is not found when github has no such account, which it remembers', function (): void {
    Http::fake(['api.github.com/users/everyone' => Http::response(['message' => 'Not Found'], 404)]);

    mentionOf('everyone');

    $this->get('/@everyone')->assertNotFound();
    $this->get('/@everyone')->assertNotFound();

    Http::assertSentCount(1);
});

it('is not found for an organization, since nobody can sign in as one', function (): void {
    Http::fake(['api.github.com/users/laravel' => Http::response(['id' => 958072, 'login' => 'laravel', 'type' => 'Organization', 'name' => 'Laravel'])]);

    mentionOf('laravel');

    $this->get('/@laravel')->assertNotFound();
});

it('is not found while github cannot be reached, and asks again a minute later', function (): void {
    Http::fakeSequence('api.github.com/users/ondrejmirtes')
        ->pushFailedConnection()
        ->push(['id' => 104888, 'login' => 'ondrejmirtes', 'type' => 'User', 'name' => 'Ondřej Mirtes']);

    mentionOf('ondrejmirtes');

    $this->get('/@ondrejmirtes')->assertNotFound();
    $this->get('/@ondrejmirtes')->assertNotFound();

    $this->travel(61)->seconds();

    $this->get('/@ondrejmirtes')->assertOk();

    Http::assertSentCount(2);
});

it('remembers the github account for a day', function (): void {
    fakeUnclaimedAccount();

    mentionOf('ondrejmirtes');

    $this->get('/@ondrejmirtes')->assertOk();
    $this->get('/@ondrejmirtes')->assertOk();

    Http::assertSentCount(1);
});

it('ignores what github answers that is not an account', function (): void {
    Http::fake(['api.github.com/users/ondrejmirtes' => Http::response(['message' => 'Moved'])]);

    mentionOf('ondrejmirtes');

    $this->get('/@ondrejmirtes')->assertNotFound();
});

it('sends a renamed account to the profile its owner already has', function (): void {
    $owner = User::factory()->create(['username' => 'oldname', 'github_id' => '104888']);

    fakeUnclaimedAccount('newname');
    mentionOf('newname');

    $this->get('/@newname')->assertRedirect(route('user.show', $owner));
});

it('shows the profile of a user who signed in rather than the one waiting for them', function (): void {
    Http::fake();

    $user = User::factory()->create(['username' => 'ondrejmirtes']);

    mentionOf('ondrejmirtes');

    $this->get('/@ondrejmirtes')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('user/show')
            ->where('user.id', $user->id));

    Http::assertNothingSent();
});

it('does not count who is waiting when loading more mentions', function (): void {
    fakeUnclaimedAccount();

    mentionOf('ondrejmirtes');

    $this->get('/@ondrejmirtes')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->reloadOnly('commits', fn (AssertableInertia $reload): AssertableInertia => $reload
                ->missing('waiting')
                ->has('commits.data', 1)));
});

it('leaves out of who is waiting the people across a block from the viewer', function (): void {
    fakeUnclaimedAccount();

    $viewer = User::factory()->create();
    $blocked = User::factory()->create();
    $blocker = User::factory()->create();
    $visible = PendingFollow::factory()->create(['github_id' => '104888']);

    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    PendingFollow::factory()->create(['user_id' => $blocked->id, 'github_id' => '104888']);
    PendingFollow::factory()->create(['user_id' => $blocker->id, 'github_id' => '104888']);
    mentionOf('ondrejmirtes');

    $this->actingAs($viewer)
        ->get('/@ondrejmirtes')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('waiting.count', 3)
            ->has('waiting.users', 1)
            ->where('waiting.users.0.id', $visible->user_id));
});
