<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\User;

function starsHeaders(string $data = 'tab,commits,pinnedCommit'): array
{
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'user/show',
        'X-Inertia-Partial-Data' => $data,
    ];
}

it('lists the commits and replies you starred, newest star first, across pages', function (): void {
    $user = User::factory()->create();
    $pinned = Commit::factory()->for($user)->create();
    $user->update(['pinned_commit_id' => $pinned->id]);

    $earlier = Commit::factory()->count(21)->create();
    $user->starredCommits()->attach($earlier, ['created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]);

    $parent = Commit::factory()->create(['body' => 'The parent']);
    $later = collect([
        Commit::factory()->create(),
        Commit::factory()->for($parent, 'parent')->create(),
    ]);
    $user->starredCommits()->attach($later);

    Commit::factory()->create();

    $response = $this->actingAs($user)->getJson(route('user.show', ['user' => $user, 'tab' => 'stars']), starsHeaders());

    $expected = [
        ...$later->sortByDesc('id')->pluck('id'),
        ...$earlier->sortByDesc('id')->pluck('id'),
    ];

    $response->assertOk()
        ->assertJsonPath('props.tab', 'stars')
        ->assertJsonPath('props.pinnedCommit', null)
        ->assertJsonPath('props.commits.data.'.array_search($later[1]->id, $expected, true).'.parent.body', 'The parent');

    expect(array_column($response->json('props.commits.data'), 'id'))->toBe(array_slice($expected, 0, 20));

    $next = $this->actingAs($user)->getJson($response->json('props.commits.next_page_url'), starsHeaders('commits'));

    $next->assertOk();

    expect(array_column($next->json('props.commits.data'), 'id'))->toBe(array_slice($expected, 20));
});

it('leaves out starred commits you can no longer see', function (): void {
    $user = User::factory()->create();
    $visible = Commit::factory()->create();
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();

    $blocker->blocking()->attach($user);
    $user->blocking()->attach($blocked);

    $deleted = Commit::factory()->create();

    $user->starredCommits()->attach([
        $visible->id,
        $deleted->id,
        Commit::factory()->pending()->create()->id,
        Commit::factory()->rejected()->create()->id,
        Commit::factory()->for($blocker)->create()->id,
        Commit::factory()->for($blocked)->create()->id,
    ]);

    $deleted->delete();

    $response = $this->actingAs($user)->getJson(route('user.show', ['user' => $user, 'tab' => 'stars']), starsHeaders());

    $response->assertOk();

    expect(array_column($response->json('props.commits.data'), 'id'))->toBe([$visible->id]);
});

it('renders the stars tab on your own profile', function (): void {
    $user = User::factory()->create();
    $user->starredCommits()->attach(Commit::factory()->create());

    $this->actingAs($user)
        ->get(route('user.show', ['user' => $user, 'tab' => 'stars']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/show')
            ->where('tab', 'stars')
            ->loadDeferredProps(fn ($reload) => $reload)
            ->has('commits.data', 1));
});

it('hides the stars of other people as if the tab did not exist', function (?bool $signedIn): void {
    $user = User::factory()->create();
    $user->starredCommits()->attach(Commit::factory()->create());

    if ($signedIn === true) {
        $this->actingAs(User::factory()->create());
    }

    $this->get(route('user.show', ['user' => $user, 'tab' => 'stars']))->assertNotFound();
})->with([
    'another user' => [true],
    'a guest' => [null],
]);
