<?php

declare(strict_types=1);

use App\Actions\UpdateGuildCommitPin;
use App\Enums\Feature;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Commit;
use App\Models\Guild;
use App\Models\User;
use App\Models\UserTimeout;
use App\Services\Features;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config(['app.admins' => ['moderator', 'viewer']]);
});

it('lets the moderator pin board posts hashtag commits and reply-matched threads', function (string $kind): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'rust']);
    $member = User::factory()->create();
    $guild->members()->attach($member, ['joined_at' => now()]);
    $commit = Commit::factory()->create([
        'body' => $kind === 'hashtag' ? 'Learning #RUST' : 'An older thread',
        'guild_id' => $kind === 'board' ? $guild->id : null,
        'created_at' => now()->subYear(),
    ]);
    $listed = $kind === 'reply'
        ? Commit::factory()->for($commit, 'parent')->create(['body' => 'Discuss #rust here'])
        : $commit;

    $newer = Commit::factory()->for($guild)->create();
    Event::fake([UsersUpdated::class]);

    $this->actingAs($moderator)->put(route('guild-commit-pin.update', [$guild->handle, $commit]), ['pinned' => true])
        ->assertRedirectToRoute('guild.show', $guild->handle);

    expect($guild->fresh())->pinned_commit_id->toBe($commit->id)->updated_at->toEqual($guild->updated_at);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds
        && collect($event->userIds)->sort()->values()->all() === collect([$moderator->id, $member->id])->sort()->values()->all());
    $this->actingAs(User::factory()->create(['handle' => 'viewer']))->get(route('guild.show', $guild->handle))
        ->assertInertia(fn ($page) => $page->where('pinnedCommit.id', $commit->id)->where('canModerate', false)
            ->where('commits.0.id', $newer->id)->where('commits.1.id', $listed->id)->has('commits', 2));
})->with(['board', 'hashtag', 'reply']);

it('replaces pins and ignores stale unpins without changing profile pins', function (): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id]);
    $first = Commit::factory()->for($guild)->create();
    $next = Commit::factory()->for($guild)->create();
    $profilePin = Commit::factory()->for($moderator)->create();
    $moderator->update(['pinned_commit_id' => $profilePin->id]);
    $this->actingAs($moderator);

    $this->put(route('guild-commit-pin.update', [$guild->handle, $first]), ['pinned' => true])->assertRedirect();
    $this->put(route('guild-commit-pin.update', [$guild->handle, $next]), ['pinned' => true])->assertRedirect();
    $this->put(route('guild-commit-pin.update', [$guild->handle, $next]), ['pinned' => true])->assertRedirect();
    $this->put(route('guild-commit-pin.update', [$guild->handle, $first]), ['pinned' => false])->assertRedirect();
    expect($guild->fresh()->pinned_commit_id)->toBe($next->id);
    $this->put(route('guild-commit-pin.update', [$guild->handle, $next]), ['pinned' => false])->assertRedirect();
    $this->put(route('guild-commit-pin.update', [$guild->handle, $next]), ['pinned' => false])->assertRedirect();

    expect($guild->fresh()->pinned_commit_id)->toBeNull();
    expect($moderator->fresh()->pinned_commit_id)->toBe($profilePin->id);
    $this->get(route('guild.show', $guild->handle))->assertInertia(fn ($page) => $page
        ->where('pinnedCommit', null)->where('commits.0.id', $next->id)->where('commits.1.id', $first->id));
});

it('returns an old pin separately without changing chronological page boundaries', function (): void {
    $viewer = User::factory()->create(['handle' => 'viewer']);
    $guild = Guild::factory()->create();
    $pinned = Commit::factory()->for($guild)->create(['created_at' => now()->subYear()]);
    $newer = Commit::factory()->for($guild)->count(31)->create();
    $guild->update(['pinned_commit_id' => $pinned->id]);

    $this->actingAs($viewer)->get(route('guild.show', $guild->handle))->assertInertia(fn ($page) => $page
        ->where('commits', fn ($rows): bool => collect($rows)->pluck('id')->all() === $newer->reverse()->take(30)->values()->modelKeys())
        ->where('pinnedCommit.id', $pinned->id)->where('hasMore', true));
    $this->get(route('guild.show', ['guild' => $guild->handle, 'page' => 2]))->assertInertia(fn ($page) => $page
        ->where('commits', fn ($rows): bool => collect($rows)->pluck('id')->all() === [$newer[0]->id, $pinned->id])
        ->where('pinnedCommit.id', $pinned->id)->where('hasMore', false));
});

it('keeps pagination independent of pin replacement and removal by another client', function (): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id]);
    $old = Commit::factory()->for($guild)->create(['created_at' => now()->subYear()]);
    $newer = Commit::factory()->for($guild)->count(61)->create();
    $guild->update(['pinned_commit_id' => $old->id]);
    $replacement = $newer[20];
    $this->actingAs($moderator);

    $this->get(route('guild.show', $guild->handle))->assertInertia(fn ($page) => $page
        ->where('commits', fn ($rows): bool => collect($rows)->pluck('id')->all() === $newer->reverse()->take(30)->values()->modelKeys())
        ->where('pinnedCommit.id', $old->id));
    $response = $this->put(route('guild-commit-pin.update', [$guild->handle, $replacement]), ['pinned' => true, 'page' => 2]);
    $response->assertRedirectToRoute('guild.show', $guild->handle);
    $this->get(route('guild.show', ['guild' => $guild->handle, 'page' => 2, 'from_start' => 1]))->assertInertia(fn ($page) => $page
        ->where('commits', fn ($rows): bool => collect($rows)->pluck('id')->all() === $newer->reverse()->slice(30, 30)->values()->modelKeys())
        ->where('pinnedCommit.id', $replacement->id)->where('page', 2)->where('hasMore', true));

    $this->put(route('guild-commit-pin.update', [$guild->handle, $replacement]), ['pinned' => false, 'page' => PHP_INT_MAX])
        ->assertRedirectToRoute('guild.show', $guild->handle);
    $this->get(route('guild.show', ['guild' => $guild->handle, 'page' => 3]))->assertInertia(fn ($page) => $page
        ->where('commits', fn ($rows): bool => collect($rows)->pluck('id')->all() === [$newer[0]->id, $old->id])
        ->where('pinnedCommit', null)->where('hasMore', false));
    $this->get(route('guild.show', ['guild' => $guild->handle, 'page' => 21, 'from_start' => 1]))->assertInertia(fn ($page) => $page
        ->has('commits', 0)->where('page', 21)->where('hasMore', false));
    $this->getJson(route('guild.show', ['guild' => $guild->handle, 'page' => PHP_INT_MAX]))
        ->assertUnprocessable()->assertJsonValidationErrors('page');
});

it('refreshes only the pin and moderator permission after a pin mutation', function (): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id]);
    $commit = Commit::factory()->for($guild)->create();
    $response = $this->actingAs($moderator)->put(route('guild-commit-pin.update', [$guild->handle, $commit]), ['pinned' => true]);

    $this->get($response->headers->get('Location'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'guild/show',
        'X-Inertia-Partial-Data' => 'pinnedCommit,canModerate',
    ])->assertOk()->assertJsonPath('props.pinnedCommit.id', $commit->id)->assertJsonPath('props.canModerate', true)
        ->assertJsonMissingPath('props.commits')->assertJsonMissingPath('props.page')->assertJsonMissingPath('props.hasMore');
});

it('does not let a pin override visibility mutes or board membership', function (string $hidden): void {
    $viewer = User::factory()->create(['handle' => 'viewer']);
    $guild = Guild::factory()->create(['handle' => 'rust']);
    $commit = Commit::factory()->create(['body' => '#rust', 'created_at' => now()->subYear()]);
    $visible = Commit::factory()->for($guild)->create();
    $guild->update(['pinned_commit_id' => $commit->id]);
    match ($hidden) {
        'pending' => $commit->update(['status' => 'pending']),
        'deleted' => $commit->delete(),
        'muted' => $viewer->muting()->attach($commit->user),
        'blocked' => $viewer->blocking()->attach($commit->user),
        'no longer matches' => $commit->update(['body' => '#rustaceans']),
    };

    $this->actingAs($viewer)->get(route('guild.show', $guild->handle))->assertInertia(fn ($page) => $page
        ->has('commits', 1)->where('commits.0.id', $visible->id)->where('pinnedCommit', null));
})->with(['pending', 'deleted', 'muted', 'blocked', 'no longer matches']);

it('requires sign in and the guild moderator even for administrators', function (string $role): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id]);
    $commit = Commit::factory()->for($guild)->create();
    if ($role !== 'guest') {
        $this->actingAs(User::factory()->create(['handle' => $role]));
    }

    $response = $this->putJson(route('guild-commit-pin.update', [$guild->handle, $commit]), ['pinned' => true]);

    $role === 'guest' ? $response->assertUnauthorized() : $response->assertNotFound();
    expect($guild->fresh()->pinned_commit_id)->toBeNull();
})->with(['guest', 'member', 'viewer']);

it('refuses replies unrelated commits and posts belonging to another guild', function (string $kind): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'rust']);
    $commit = match ($kind) {
        'reply' => Commit::factory()->for(Commit::factory()->for($guild), 'parent')->create(['body' => '#rust']),
        'other board' => Commit::factory()->for(Guild::factory())->create(['body' => '#rust']),
        'unrelated' => Commit::factory()->create(['body' => '`#rust` #rustaceans']),
    };

    $this->actingAs($moderator)->put(route('guild-commit-pin.update', [$guild->handle, $commit]), ['pinned' => true])->assertNotFound();

    expect($guild->fresh()->pinned_commit_id)->toBeNull();
})->with(['reply', 'other board', 'unrelated']);

it('honors the guild feature flag and validates pin input', function (): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id]);
    $commit = Commit::factory()->for($guild)->create();
    $url = route('guild-commit-pin.update', [$guild->handle, $commit]);

    $this->actingAs($moderator)->putJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('pinned');
    $this->putJson($url, ['pinned' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors('pinned');
    resolve(Features::class)->update(Feature::Guilds, false);
    $this->putJson($url, ['pinned' => true])->assertNotFound();

    expect($guild->fresh()->pinned_commit_id)->toBeNull();
});

it('refuses a moderator who loses ownership after authorization', function (): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id]);
    $commit = Commit::factory()->for($guild)->create();
    $this->app->beforeResolving(UpdateGuildCommitPin::class, function () use ($guild): void {
        $guild->update(['user_id' => null]);
    });

    $this->actingAs($moderator)->fromRoute('guild.show', $guild->handle)
        ->put(route('guild-commit-pin.update', [$guild->handle, $commit]), ['pinned' => true, 'page' => 2])
        ->assertSessionHasErrors(['pin' => 'You no longer moderate this guild.'])
        ->assertRedirectToRoute('guild.show', $guild->handle);

    expect($guild->fresh()->pinned_commit_id)->toBeNull();
});

it('keeps the current pin if the target becomes unavailable after authorization', function (string $change): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'rust']);
    $old = Commit::factory()->for($guild)->create();
    $guild->update(['pinned_commit_id' => $old->id]);
    $target = Commit::factory()->create(['body' => '#rust']);
    $this->app->beforeResolving(UpdateGuildCommitPin::class, function () use ($target, $change): void {
        match ($change) {
            'erased' => $target->forceDelete(),
            'deleted' => $target->delete(),
            'edited' => $target->update(['body' => 'No guild hashtag']),
        };
    });

    $this->actingAs($moderator)->put(route('guild-commit-pin.update', [$guild->handle, $target]), ['pinned' => true])
        ->assertSessionHasErrors(['pin' => 'This commit is no longer available on the board.']);

    expect($guild->fresh()->pinned_commit_id)->toBe($old->id);
})->with(['erased', 'deleted', 'edited']);

it('lets the moderator clear a pin that no longer qualifies without allowing invalid pin input', function (string $change): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id, 'handle' => 'rust']);
    $commit = Commit::factory()->create(['body' => '#rust']);
    $guild->update(['pinned_commit_id' => $commit->id]);
    $change === 'deleted' ? $commit->delete() : $commit->update(['body' => 'No guild hashtag']);
    $url = route('guild-commit-pin.update', [$guild->handle, $commit]);
    $this->actingAs($moderator);

    $this->put($url, ['pinned' => true])->assertNotFound();
    $this->put($url, [])->assertNotFound();
    $this->put($url, ['pinned' => 'invalid'])->assertNotFound();
    expect($guild->fresh()->pinned_commit_id)->toBe($commit->id);
    $this->put($url, ['pinned' => false])->assertRedirect()->assertSessionHasNoErrors();

    expect($guild->fresh()->pinned_commit_id)->toBeNull();
})->with(['deleted', 'edited']);

it('returns a validation error and timeout dialog when a pin is refused', function (): void {
    $moderator = User::factory()->create(['handle' => 'moderator']);
    $guild = Guild::factory()->create(['user_id' => $moderator->id]);
    $commit = Commit::factory()->for($guild)->create();
    UserTimeout::factory()->for($moderator)->create();

    $this->actingAs($moderator)->fromRoute('guild.show', $guild->handle)
        ->put(route('guild-commit-pin.update', [$guild->handle, $commit]), ['pinned' => true, 'page' => 2])
        ->assertRedirectToRoute('guild.show', $guild->handle)
        ->assertSessionHasErrors('pin')->assertInertiaFlash('timeout');

    expect($guild->fresh()->pinned_commit_id)->toBeNull();
});

it('clears the pin when its commit is permanently erased without deleting the guild', function (): void {
    $guild = Guild::factory()->create();
    $commit = Commit::factory()->for($guild)->create();
    $guild->update(['pinned_commit_id' => $commit->id]);

    $commit->forceDelete();

    expect($guild->fresh()->pinned_commit_id)->toBeNull();
});
