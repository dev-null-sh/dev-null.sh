<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('switches profile tabs with only the feed and resets its cursor data', function (string $tab): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    $pinned = Commit::factory()->for($user)->create(['body' => 'Pinned commit']);
    $user->update(['pinned_commit_id' => $pinned->id]);
    $commits = Commit::factory()->for($user)->count(23)->create(['body' => 'A profile commit']);
    $replies = Commit::factory()->for($user)->for($parent, 'parent')->count(23)->create(['body' => 'A profile reply']);

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'user/show',
        'X-Inertia-Partial-Data' => 'tab,commits,pinnedCommit',
        'X-Inertia-Reset' => 'commits',
    ];

    DB::enableQueryLog();
    DB::flushQueryLog();

    $response = $this->actingAs($user)->getJson(route('user.show', ['user' => $user, 'tab' => $tab]), $headers);

    $response->assertOk()
        ->assertJsonPath('props.tab', $tab)
        ->assertJsonCount(20, 'props.commits.data')
        ->assertJsonMissingPath('props.user')
        ->assertJsonMissingPath('props.activity')
        ->assertJsonMissingPath('props.streaks')
        ->assertJsonMissingPath('props.achievements')
        ->assertJsonPath('scrollProps.commits.reset', true);

    if ($tab === 'commits') {
        $response->assertJsonPath('props.pinnedCommit.id', $pinned->id);
    } else {
        $response->assertJsonPath('props.pinnedCommit', null);
    }

    $expected = ($tab === 'replies' ? $replies : $commits)->sortByDesc('id')->pluck('id')->values();

    expect(array_column($response->json('props.commits.data'), 'id'))->toBe($expected->take(20)->all())
        ->and(collect(DB::getQueryLog())->pluck('query')->all())
        ->each(fn ($query) => $query->not->toContain('as total_commits', 'as bucket', 'as day'));

    $next = $this->getJson($response->json('props.commits.next_page_url'), [
        ...$headers,
        'X-Inertia-Partial-Data' => 'commits',
        'X-Inertia-Reset' => '',
    ]);

    $next->assertOk()->assertJsonCount(3, 'props.commits.data');

    expect(array_column($next->json('props.commits.data'), 'id'))->toBe($expected->skip(20)->values()->all());
})->with(['commits', 'replies']);

it('lists only the profile authors replies in the replies tab', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($user)->for($parent, 'parent')->create();
    Commit::factory()->for($user)->create();
    Commit::factory()->for($parent, 'parent')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('user.show', ['user' => $user, 'tab' => 'replies']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/show')
            ->where('tab', 'replies')
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $reply->id)
            ->where('commits.data.0.parent.id', $parent->id)
            ->where('commits.data.0.parent.user.handle', $parent->user->handle)
            ->missing('commits.data.0.parent.user.email'));
});

it('defaults to top level commits', function (?string $tab): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    Commit::factory()->for($user)->for($commit, 'parent')->create();

    $this->actingAs($user)
        ->get(route('user.show', ['user' => $user, 'tab' => $tab]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'commits')
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $commit->id));
})->with([null, 'commits']);

it('respects reply moderation visibility', function (bool $isOwner, int $count): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    Commit::factory()->for($user)->for($parent, 'parent')->create();
    Commit::factory()->for($user)->for($parent, 'parent')->pending()->create();
    Commit::factory()->for($user)->for($parent, 'parent')->rejected()->create();

    $this->actingAs($isOwner ? $user : User::factory()->create())
        ->get(route('user.show', ['user' => $user, 'tab' => 'replies']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('commits.data', $count));
})->with([[true, 3], [false, 1]]);

it('does not expose a hidden parents content or author', function (CommitStatus $status): void {
    $parent = Commit::factory()->create(['status' => $status]);
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('user.show', ['user' => $reply->user, 'tab' => 'replies']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $reply->id)
            ->where('commits.data.0.parent', null));
})->with([CommitStatus::Pending, CommitStatus::Rejected]);

it('shows a parents author their own pending parent context', function (): void {
    $parent = Commit::factory()->pending()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();

    $this->actingAs($parent->user)
        ->get(route('user.show', ['user' => $reply->user, 'tab' => 'replies']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('commits.data.0.parent.id', $parent->id));
});

it('keeps replies to deleted parents without exposing the deleted content', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $parent->delete();

    $this->actingAs($reply->user)
        ->get(route('user.show', ['user' => $reply->user, 'tab' => 'replies']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $reply->id)
            ->where('commits.data.0.parent', null));
});

it('hides deleted replies from their authors profile', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    $kept = Commit::factory()->for($user)->for($parent, 'parent')->create();
    Commit::factory()->for($user)->for($parent, 'parent')->create()->delete();

    $this->actingAs($user)
        ->get(route('user.show', ['user' => $user, 'tab' => 'replies']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $kept->id));
});

it('paginates replies without mixing in root commits', function (): void {
    $user = User::factory()->create();
    $parent = Commit::factory()->create();
    $replies = Commit::factory()->for($user)->for($parent, 'parent')->count(23)->create();
    Commit::factory()->for($user)->count(4)->create();

    $response = $this->actingAs($user)
        ->get(route('user.show', ['user' => $user, 'tab' => 'replies']));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->has('commits.data', 20)
        ->where('commits.next_page_url', fn (string $url): bool => str_contains($url, 'tab=replies')));

    $firstPage = $response->viewData('page')['props']['commits'];

    $next = $this->get($firstPage['next_page_url'])->assertOk();

    $next->assertInertia(fn ($page) => $page->where('tab', 'replies')->has('commits.data', 3));

    $secondPage = $next->viewData('page')['props']['commits'];

    expect(array_column([...$firstPage['data'], ...$secondPage['data']], 'id'))
        ->toHaveCount(23)
        ->toEqualCanonicalizing($replies->modelKeys());
});

it('validates the profile tab', function (mixed $tab): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('user.show', ['user' => $user, 'tab' => $tab]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tab');
})->with(['unknown' => ['unknown'], 'array' => [['replies']]]);

it('shows guests the approved replies', function (): void {
    $reply = Commit::factory()->for(Commit::factory(), 'parent')->create();
    $pending = Commit::factory()->for($reply->user)->for(Commit::factory(), 'parent')->pending()->create();

    $this->get(route('user.show', ['user' => $reply->user, 'tab' => 'replies']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/show')
            ->has('commits.data', 1)
            ->where('commits.data.0.id', $reply->id))
        ->assertDontSee($pending->body);

    expect(session('url.intended'))->toBe(route('user.show', ['user' => $reply->user, 'tab' => 'replies']));
});
