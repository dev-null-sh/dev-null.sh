<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

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
            ->where('commits.data.0.parent.user.username', $parent->user->username)
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

it('keeps replies behind sign in for guests', function (): void {
    $reply = Commit::factory()->for(Commit::factory(), 'parent')->create();

    $this->get(route('user.show', ['user' => $reply->user, 'tab' => 'replies']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('welcome')->missing('commits'))
        ->assertDontSee($reply->body);

    expect(session('url.intended'))->toBe(route('user.show', ['user' => $reply->user, 'tab' => 'replies']));
});

it('returns to the replies tab after local sign in', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', true);

    $user = User::factory()->create();
    $url = route('user.show', ['user' => $user, 'tab' => 'replies']);

    $this->get($url)->assertRedirectToRoute('home');
    $this->get(route('home'))->assertRedirect($url);
});
