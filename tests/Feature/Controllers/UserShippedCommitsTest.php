<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('lists only that user\'s visible shipped commits on the shipped tab', function (): void {
    $user = User::factory()->create();
    $shipped = Commit::factory()->for($user)->create([
        'shipped' => true,
        'body' => 'Shipped the parser',
        'created_at' => now()->subMinute(),
    ]);
    $pending = Commit::factory()->for($user)->pending()->create([
        'shipped' => true,
        'body' => 'Pending shipped secret',
    ]);
    $pinned = Commit::factory()->for($user)->create([
        'shipped' => true,
        'body' => 'Pinned and shipped',
        'created_at' => now()->subMinutes(2),
    ]);
    $user->update(['pinned_commit_id' => $pinned->id]);
    $rejected = Commit::factory()->for($user)->rejected()->create([
        'shipped' => true,
        'body' => 'Rejected shipped secret',
        'created_at' => now()->subMinutes(3),
    ]);
    Commit::factory()->for($user)->create(['body' => 'Not a shipping announcement']);
    Commit::factory()->for($user)->for($shipped, 'parent')->create(['shipped' => true, 'body' => 'A shipped reply']);
    $other = Commit::factory()->create(['shipped' => true, 'body' => 'Someone else shipped this']);

    $this->get(route('user.show', ['user' => $user, 'tab' => 'shipped']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'shipped')
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $shipped->id)
            ->where('commits.data.0.shipped', true)
            ->where('commits.data.1.id', $pinned->id))
        ->assertDontSee('Pending shipped secret', false)
        ->assertDontSee('Rejected shipped secret', false)
        ->assertDontSee('Someone else shipped this', false)
        ->assertDontSee('Not a shipping announcement', false);

    $this->actingAs(User::factory()->create())
        ->get(route('user.show', ['user' => $user, 'tab' => 'shipped']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $shipped->id)
            ->where('commits.data.1.id', $pinned->id))
        ->assertDontSee($other->body, false);

    $this->actingAs($user)
        ->get(route('user.show', ['user' => $user, 'tab' => 'shipped']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user/show')
            ->where('tab', 'shipped')
            ->where('pinnedCommit', null)
            ->has('commits.data', 4)
            ->where('commits.data.0.id', $pending->id)
            ->where('commits.data.0.shipped', true)
            ->where('commits.data.1.id', $shipped->id)
            ->where('commits.data.2.id', $pinned->id)
            ->where('commits.data.3.id', $rejected->id));

    $this->get(route('commit.show', [$user, $shipped]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('commit.shipped', true));
});

it('keeps shipped commits on the commits tab and leaves a pinned one above the list', function (): void {
    $user = User::factory()->create();
    $pinned = Commit::factory()->for($user)->create(['shipped' => true, 'created_at' => now()->subMinutes(2)]);
    $user->update(['pinned_commit_id' => $pinned->id]);
    $other = Commit::factory()->for($user)->create(['shipped' => true, 'created_at' => now()->subMinute()]);
    $plain = Commit::factory()->for($user)->create(['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('user.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'commits')
            ->where('pinnedCommit.id', $pinned->id)
            ->where('pinnedCommit.shipped', true)
            ->has('commits.data', 2)
            ->where('commits.data.0.id', $plain->id)
            ->missing('commits.data.0.shipped')
            ->where('commits.data.1.id', $other->id)
            ->where('commits.data.1.shipped', true));
});

it('rejects an unknown profile tab', function (): void {
    $user = User::factory()->create();

    $this->getJson(route('user.show', ['user' => $user, 'tab' => 'stars']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tab');
});
