<?php

declare(strict_types=1);

use App\Actions\CreateCommitReaction;
use App\Actions\DeleteCommit;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\RepositoryMirror;
use App\Models\RepositoryMirrorEvent;
use App\Models\User;
use App\Services\RepositoryMirrors;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function connectedMirror(User $user, array $attributes = []): RepositoryMirror
{
    $mirror = new RepositoryMirror;
    $mirror->forceFill([
        'user_id' => $user->id, 'repository' => 'reader/archive', 'repository_id' => '42',
        'branch' => 'main', 'private' => true, 'token' => 'test-mirror-token', 'mirror_commits' => true,
        ...$attributes,
    ])->save();

    return $mirror;
}

it('supports Unicode emoji sequences beyond the original six flavors', function (string $emoji): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => $emoji])
        ->assertOk()->assertJsonPath('reactions.0.emoji', $emoji)->assertJsonPath('stars_count', 0);
})->with(['🦦', '👍🏽', '👩🏽‍💻', '🇺🇦', '🫩']);

it('keeps repository-only reactions out of public stars, notifications and ranking', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    $commit = Commit::factory()->create(['body' => 'Someone else’s text never goes to the reactor’s repo']);
    $score = $commit->hot_score;
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '🦦', 'public' => false, 'repositories' => [$mirror->id]])
        ->assertOk()->assertJsonPath('starred', false)->assertJsonPath('stars_count', 0)->assertJsonPath('star_flavors', []);
    expect($commit->stars()->count())->toBe(0)
        ->and($commit->user->notifications()->count())->toBe(0)
        ->and($commit->fresh()->hot_score)->toEqualWithDelta($score, 1e-9);
    $event = RepositoryMirrorEvent::query()->sole();
    expect($event->repository_mirror_id)->toBe($mirror->id)
        ->and($event->payload['reaction'])->toBe(['emoji' => '🦦', 'visibility' => 'repository'])
        ->and($event->payload['commit'])->not->toHaveKey('body');
    $other = User::factory()->create();
    $this->actingAs($other)->getJson(route('commit-reaction-state', $commit))->assertJsonPath('repositories', []);
    $this->actingAs($user)->getJson(route('commit-reaction-state', $commit))->assertJsonPath('repositories.0.flavor', '🦦')->assertHeader('Cache-Control', 'no-store, private');
});

it('does not duplicate an event for an unchanged reaction and preserves created time', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    $commit = Commit::factory()->create();
    foreach (range(1, 2) as $attempt) {
        $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '🚀', 'public' => true, 'repositories' => [$mirror->id]])->assertOk();
    }
    expect(RepositoryMirrorEvent::query()->count())->toBe(1)->and($commit->stars()->count())->toBe(0);
});

it('reconciles public, repository-only and both destinations', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    $commit = Commit::factory()->create();
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '🚀', 'public' => true, 'repositories' => [$mirror->id]])->assertOk()->assertJsonPath('stars_count', 0);
    $this->postJson(route('commit-reaction.store', $commit), ['flavor' => '🚀', 'public' => false, 'repositories' => [$mirror->id]])->assertOk()->assertJsonPath('stars_count', 0);
    expect($commit->user->notifications()->count())->toBe(0);
    $this->postJson(route('commit-reaction.store', $commit), ['flavor' => '🚀', 'public' => true, 'repositories' => []])->assertOk()->assertJsonPath('stars_count', 0);
    expect(DB::table('repository_reactions')->count())->toBe(0)
        ->and(RepositoryMirrorEvent::query()->orderByDesc('id')->first()->payload['type'])->toBe('reaction.removed');
});

it('clears a repository-only reaction without inventing a public star', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    $commit = Commit::factory()->create();
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '🦦', 'public' => false, 'repositories' => [$mirror->id]])->assertOk();
    $this->deleteJson(route('commit-reaction.destroy', $commit))->assertOk()->assertJsonPath('stars_count', 0);
    $this->deleteJson(route('commit-reaction.destroy', $commit))->assertOk();
    expect(RepositoryMirrorEvent::query()->count())->toBe(2)->and(DB::table('repository_reactions')->count())->toBe(0);
});

it('requires a connected repository and a valid destination', function (array $destinations): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '🔥', ...$destinations])->assertUnprocessable();
    expect($commit->stars()->count())->toBe(0)->and(RepositoryMirrorEvent::query()->count())->toBe(0);
})->with([[['public' => false, 'repositories' => []]], [['public' => false, 'repositories' => ['00000000-0000-0000-0000-000000000001']]], [['public' => 'garbage']]]);

it('rejects non-emoji and multiple emoji', function (mixed $flavor): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => $flavor])->assertUnprocessable();
})->with(['hello', '🚀🔥', '<script>', [['🔥']]]);

it('requires visibility even for repository-only reactions and personal state', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    $commit = Commit::factory()->pending()->create();
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '🔥', 'public' => false, 'repositories' => [$mirror->id]])->assertForbidden();
    $this->getJson(route('commit-reaction-state', $commit))->assertNotFound();
    expect(RepositoryMirrorEvent::query()->count())->toBe(0);
});

it('rolls back mirror events with the source transaction', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    $commit = Commit::factory()->create();
    DB::beginTransaction();
    resolve(CreateCommitReaction::class)->handle($user, $commit, '🦦', true, [$mirror->id]);
    DB::rollBack();
    expect(RepositoryMirrorEvent::query()->count())->toBe(0)->and($commit->stars()->count())->toBe(0);
});

it('queues approval and deletion of the author’s own commits without copying pending text', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    $commit = Commit::factory()->for($user)->pending()->create(['body' => 'Private until approved']);
    resolve(RepositoryMirrors::class)->commit($commit);
    expect(RepositoryMirrorEvent::query()->count())->toBe(0);
    resolve(UpdateCommitStatuses::class)->handle([$commit->id], CommitStatus::Approved);
    expect(RepositoryMirrorEvent::query()->sole()->payload['commit']['body'])->toBe('Private until approved');
    resolve(DeleteCommit::class)->handle($commit);
    $removed = RepositoryMirrorEvent::query()->orderByDesc('id')->first()->payload;
    expect($removed['type'])->toBe('commit.removed')->and($removed['commit'])->not->toHaveKey('body');
});

it('can disable automatic commit mirrors without disabling repository reactions', function (): void {
    $user = User::factory()->create();
    connectedMirror($user, ['mirror_commits' => false]);
    $commit = Commit::factory()->for($user)->create();
    resolve(RepositoryMirrors::class)->commit($commit);
    expect(RepositoryMirrorEvent::query()->count())->toBe(0);
});

it('checks token identity and connects only the signed-in user’s destination', function (): void {
    $user = User::factory()->create(['github_id' => '17']);
    Http::fake([
        'api.github.com/user' => Http::response(['id' => 17]),
        'api.github.com/repos/reader/archive' => Http::response(['id' => 42, 'full_name' => 'reader/archive', 'default_branch' => 'main', 'private' => true, 'archived' => false, 'disabled' => false, 'permissions' => ['push' => true]]),
        'api.github.com/repos/reader/archive/branches/main' => Http::response(['name' => 'main']),
    ]);
    $this->actingAs($user)->post(route('repository-mirror.store'), ['repository' => 'reader/archive', 'token' => 'secret-test-token', 'mirror_commits' => true])->assertRedirect()->assertSessionHasNoErrors();
    $mirror = RepositoryMirror::query()->sole();
    expect($mirror->token)->toBe('secret-test-token')->and($mirror->toArray())->not->toHaveKey('token');
    expect(DB::table('repository_mirrors')->value('token'))->not->toContain('secret-test-token');
    Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
});

it('never flashes a rejected token back to the session', function (): void {
    $user = User::factory()->create(['github_id' => '17']);
    Http::fake(['api.github.com/user' => Http::response(['id' => 99])]);
    $this->actingAs($user)->post(route('repository-mirror.store'), ['repository' => 'reader/archive', 'token' => 'secret-test-token', 'mirror_commits' => true])->assertSessionHasErrors('token')->assertSessionMissing('_old_input.token');
    expect(RepositoryMirror::query()->count())->toBe(0);
});

it('disconnects pending copies but preserves public stars', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    $commit = Commit::factory()->create();
    resolve(CreateCommitReaction::class)->handle($user, $commit, '🔥', true, [$mirror->id]);
    $this->actingAs($user)->delete(route('repository-mirror.destroy', $mirror))->assertRedirect();
    expect(RepositoryMirror::query()->count())->toBe(0)->and(RepositoryMirrorEvent::query()->count())->toBe(0)
        ->and(DB::table('repository_reactions')->count())->toBe(0)->and($commit->stars()->count())->toBe(0);
});

it('writes an immutable JSON event to the selected repository and branch', function (): void {
    $user = User::factory()->create(['github_id' => '17']);
    $mirror = connectedMirror($user);
    resolve(CreateCommitReaction::class)->handle($user, Commit::factory()->create(), '🚀', false, [$mirror->id]);
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/contents/')) {
            return $request->method() === 'GET' ? Http::response([], 404) : Http::response(['commit' => ['sha' => 'abc']], 201);
        }

        return Http::response(['id' => 42, 'private' => true]);
    });
    $this->artisan('mirrors:deliver')->assertSuccessful();
    expect(RepositoryMirrorEvent::query()->sole()->delivered_at)->not->toBeNull();
    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PUT') {
            return false;
        }
        $payload = json_decode(base64_decode($request['content']), true);

        return str_contains($request->url(), '/dev-null/17/reactions/') && $request['branch'] === 'main'
            && $payload['reaction']['emoji'] === '🚀' && $payload['reaction']['visibility'] === 'repository';
    });
});

it('recovers an accepted write without writing the event twice', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    resolve(CreateCommitReaction::class)->handle($user, Commit::factory()->create(), '🔥', false, [$mirror->id]);
    $event = RepositoryMirrorEvent::query()->sole();
    $content = json_encode(['event_id' => $event->id, ...$event->payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    Http::fake([
        'api.github.com/repos/reader/archive' => Http::response(['id' => 42, 'private' => true]),
        'api.github.com/repos/reader/archive/contents/*' => Http::response(['content' => base64_encode($content)]),
    ]);
    $this->artisan('mirrors:deliver')->assertSuccessful();
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
});

it('retains failures and refuses a private repository that became public', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    resolve(CreateCommitReaction::class)->handle($user, Commit::factory()->create(), '🔥', false, [$mirror->id]);
    Http::fake(['api.github.com/repos/reader/archive' => Http::response(['id' => 42, 'private' => false])]);
    $this->artisan('mirrors:deliver')->assertFailed();
    expect(RepositoryMirrorEvent::query()->sole()->delivered_at)->toBeNull()
        ->and(RepositoryMirror::query()->sole()->last_error)->not->toBeNull();
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'PUT');
});

it('saves to multiple repositories and removes only deselected destinations', function (): void {
    $user = User::factory()->create();
    $private = connectedMirror($user);
    $public = connectedMirror($user, ['repository_id' => '43', 'repository' => 'reader/public-log', 'private' => false]);
    $commit = Commit::factory()->create();
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '🚀', 'public' => false, 'repositories' => [$private->id, $public->id]])->assertOk()->assertJsonPath('stars_count', 0);
    expect(RepositoryMirrorEvent::query()->count())->toBe(2)->and(DB::table('repository_reactions')->count())->toBe(2);
    $this->getJson(route('commit-reaction-state', $commit))->assertJsonPath('public', false)->assertJsonCount(2, 'repositories');
    $this->postJson(route('commit-reaction.store', $commit), ['flavor' => '🚀', 'public' => false, 'repositories' => [$private->id]])->assertOk();
    expect(RepositoryMirrorEvent::query()->count())->toBe(3)
        ->and(RepositoryMirrorEvent::query()->latest('id')->first()->repository_mirror_id)->toBe($public->id)
        ->and(RepositoryMirrorEvent::query()->latest('id')->first()->payload['type'])->toBe('reaction.removed')
        ->and(DB::table('repository_reactions')->sole()->repository_mirror_id)->toBe($private->id);
});

it('rejects another user’s repository selection and disconnect', function (): void {
    $user = User::factory()->create();
    $foreign = connectedMirror(User::factory()->create());
    $commit = Commit::factory()->create();
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '🔥', 'public' => true, 'repositories' => [$foreign->id]])->assertUnprocessable();
    $this->delete(route('repository-mirror.destroy', $foreign))->assertForbidden();
    expect(RepositoryMirrorEvent::query()->count())->toBe(0)->and($commit->stars()->count())->toBe(0)->and($foreign->fresh())->not->toBeNull();
});

it('disconnects one repository while preserving other selected destinations', function (): void {
    $user = User::factory()->create();
    $first = connectedMirror($user);
    $second = connectedMirror($user, ['repository_id' => '43', 'repository' => 'reader/second']);
    $commit = Commit::factory()->create();
    resolve(CreateCommitReaction::class)->handle($user, $commit, '🔥', true, [$first->id, $second->id]);
    $this->actingAs($user)->delete(route('repository-mirror.destroy', $first))->assertRedirect();
    expect(RepositoryMirror::query()->sole()->id)->toBe($second->id)
        ->and(DB::table('repository_reactions')->sole()->repository_mirror_id)->toBe($second->id)
        ->and(RepositoryMirrorEvent::query()->sole()->repository_mirror_id)->toBe($second->id)
        ->and($commit->stars()->count())->toBe(0);
});

it('mirrors approved posts to each repository with automatic mirroring enabled', function (): void {
    $user = User::factory()->create();
    $first = connectedMirror($user);
    $second = connectedMirror($user, ['repository_id' => '43']);
    connectedMirror($user, ['repository_id' => '44', 'mirror_commits' => false]);
    resolve(RepositoryMirrors::class)->commit(Commit::factory()->for($user)->create());
    expect(RepositoryMirrorEvent::query()->pluck('repository_mirror_id')->all())->toEqualCanonicalizing([$first->id, $second->id]);
});

it('adds a second connection without cancelling the first connection’s queued activity', function (): void {
    $user = User::factory()->create(['github_id' => '17']);
    $first = connectedMirror($user);
    resolve(CreateCommitReaction::class)->handle($user, Commit::factory()->create(), '🔥', false, [$first->id]);
    Http::fake([
        'api.github.com/user' => Http::response(['id' => 17]),
        'api.github.com/repos/reader/second' => Http::response(['id' => 43, 'full_name' => 'reader/second', 'default_branch' => 'main', 'private' => false, 'archived' => false, 'disabled' => false, 'permissions' => ['push' => true]]),
        'api.github.com/repos/reader/second/branches/main' => Http::response(['name' => 'main']),
    ]);
    $this->actingAs($user)->post(route('repository-mirror.store'), ['repository' => 'reader/second', 'token' => 'second-test-token', 'mirror_commits' => false])->assertSessionHasNoErrors();
    expect(RepositoryMirror::query()->count())->toBe(2)->and(RepositoryMirrorEvent::query()->sole()->repository_mirror_id)->toBe($first->id)
        ->and(DB::table('repository_reactions')->sole()->repository_mirror_id)->toBe($first->id);
});

it('preserves pending activity when renewing a token for the same destination', function (): void {
    $user = User::factory()->create(['github_id' => '17']);
    $mirror = connectedMirror($user);
    resolve(CreateCommitReaction::class)->handle($user, Commit::factory()->create(), '🔥', false, [$mirror->id]);
    Http::fake([
        'api.github.com/user' => Http::response(['id' => 17]),
        'api.github.com/repos/reader/archive' => Http::response(['id' => 42, 'full_name' => 'reader/archive', 'default_branch' => 'main', 'private' => true, 'archived' => false, 'disabled' => false, 'permissions' => ['push' => true]]),
        'api.github.com/repos/reader/archive/branches/main' => Http::response(['name' => 'main']),
    ]);
    $this->actingAs($user)->post(route('repository-mirror.store'), ['repository' => 'reader/archive', 'token' => 'replacement-test-token', 'mirror_commits' => false])->assertSessionHasNoErrors();
    expect(RepositoryMirror::query()->sole()->id)->toBe($mirror->id)->and($mirror->fresh()->token)->toBe('replacement-test-token')
        ->and(RepositoryMirrorEvent::query()->count())->toBe(1)->and(DB::table('repository_reactions')->count())->toBe(1);
});

it('keeps multiple public emoji and removes each independently', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    foreach (['🚀', '🔥', '🦦', '🚀'] as $emoji) {
        $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => $emoji])->assertOk();
    }
    expect(DB::table('commit_reactions')->count())->toBe(3)->and($commit->stars()->count())->toBe(0)->and($commit->user->notifications()->count())->toBe(0);
    $this->deleteJson(route('commit-reaction.destroy', $commit), ['flavor' => '🔥'])->assertOk()->assertJsonCount(2, 'reactions')->assertJsonPath('stars_count', 0);
    expect(DB::table('commit_reactions')->pluck('flavor')->all())->toEqualCanonicalizing(['🚀', '🦦']);
    expect($commit->user->notifications()->count())->toBe(0);
    $this->deleteJson(route('commit-reaction.destroy', $commit), ['flavor' => '🚀'])->assertOk();
    $this->deleteJson(route('commit-reaction.destroy', $commit), ['flavor' => '🦦'])->assertOk()->assertJsonPath('stars_count', 0);
    expect($commit->user->notifications()->count())->toBe(0);
});

it('keeps independent destinations and encrypted copies for each emoji', function (): void {
    $user = User::factory()->create();
    $private = connectedMirror($user);
    $other = connectedMirror($user, ['repository_id' => '43', 'repository' => 'reader/other']);
    $commit = Commit::factory()->create();
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '🚀', 'public' => false, 'repositories' => [$private->id]])->assertOk();
    $this->postJson(route('commit-reaction.store', $commit), ['flavor' => '🔥', 'public' => true, 'repositories' => [$private->id, $other->id]])->assertOk()->assertJsonCount(1, 'reactions');
    expect(DB::table('repository_reactions')->count())->toBe(3)->and(RepositoryMirrorEvent::query()->count())->toBe(3);
    $this->getJson(route('commit-reaction-state', $commit))->assertJsonCount(2, 'reactions');
    $this->deleteJson(route('commit-reaction.destroy', $commit), ['flavor' => '🚀'])->assertOk()->assertJsonCount(1, 'reactions')->assertJsonPath('stars_count', 0);
    expect(DB::table('repository_reactions')->count())->toBe(2)->and(RepositoryMirrorEvent::query()->latest('id')->first()->payload['reaction']['emoji'])->toBe('🚀');
    $this->deleteJson(route('commit-reaction.destroy', $commit))->assertOk()->assertJsonCount(0, 'reactions');
    expect(DB::table('repository_reactions')->count())->toBe(0)->and(RepositoryMirrorEvent::query()->count())->toBe(6);
});

it('counts each public emoji independently for multiple people', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $commit = Commit::factory()->create();
    resolve(CreateCommitReaction::class)->handle($first, $commit, '🚀');
    resolve(CreateCommitReaction::class)->handle($first, $commit, '🔥');
    resolve(CreateCommitReaction::class)->handle($second, $commit, '🚀');
    $reactions = collect(Commit::query()->withStarsFor($first)->findOrFail($commit->id)->reactions)->keyBy('emoji');
    expect($reactions['🚀'])->toMatchArray(['emoji' => '🚀', 'count' => 2, 'reacted' => true])
        ->and(array_column($reactions['🚀']['users'], 'id'))->toEqualCanonicalizing([$first->id, $second->id])
        ->and($reactions['🔥'])->toBe(['emoji' => '🔥', 'count' => 1, 'reacted' => true, 'users' => [$first->only(['id', 'name', 'username'])]]);
});

it('treats legacy flavor aliases and their emoji as the same reaction', function (): void {
    $user = User::factory()->create();
    $mirror = connectedMirror($user);
    $commit = Commit::factory()->create();
    resolve(CreateCommitReaction::class)->handle($user, $commit, 'fire', true, [$mirror->id]);
    resolve(CreateCommitReaction::class)->handle($user, $commit, '🔥', true, [$mirror->id]);
    expect(DB::table('commit_reactions')->count())->toBe(1)->and(DB::table('repository_reactions')->count())->toBe(1)
        ->and(RepositoryMirrorEvent::query()->count())->toBe(1);
    $this->actingAs($user)->deleteJson(route('commit-reaction.destroy', $commit), ['flavor' => 'fire'])->assertOk()->assertJsonCount(0, 'reactions');
    expect(DB::table('repository_reactions')->count())->toBe(0);
});

it('keeps private reactors out of public hover names', function (): void {
    $public = User::factory()->create();
    $private = User::factory()->create();
    $commit = Commit::factory()->create();
    $mirror = connectedMirror($private);
    resolve(CreateCommitReaction::class)->handle($public, $commit, '🚀');
    resolve(CreateCommitReaction::class)->handle($private, $commit, '🚀', false, [$mirror->id]);
    $reaction = Commit::query()->withStarsFor(null)->findOrFail($commit->id)->reactions[0];
    expect($reaction['count'])->toBe(1)
        ->and($reaction['users'])->toBe([$public->only(['id', 'name', 'username'])]);
});

it('bootstraps only the signed-in viewers repository labels without credentials', function (): void {
    $viewer = User::factory()->create();
    $mine = connectedMirror($viewer);
    connectedMirror(User::factory()->create());
    $this->actingAs($viewer)->get(route('user-profile.edit'))->assertOk()->assertInertia(fn ($page) => $page
        ->has('auth.reactionRepositories', 1)
        ->where('auth.reactionRepositories.0.id', $mine->id)
        ->where('auth.reactionRepositories.0.private', true)
        ->missing('auth.reactionRepositories.0.token')
        ->etc());
});

it('keeps stars and emoji reactions fully independent including a star emoji', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $this->actingAs($user)->postJson(route('commit-star.store', $commit))->assertOk();
    $star = DB::table('stars')->where('user_id', $user->id)->where('commit_id', $commit->id)->first();
    $score = $commit->fresh()->hot_score;
    foreach (['⭐', '🔥', '🚀', '🦦', '😁'] as $emoji) {
        $this->postJson(route('commit-reaction.store', $commit), ['flavor' => $emoji])->assertOk()->assertJsonPath('stars_count', 1);
    }
    expect($commit->fresh()->hot_score)->toBe($score)
        ->and(DB::table('stars')->where('user_id', $user->id)->where('commit_id', $commit->id)->first())->toEqual($star)
        ->and($commit->user->notifications()->count())->toBe(1);
    $this->deleteJson(route('commit-star.destroy', $commit))->assertOk()->assertJsonPath('stars_count', 0);
    expect(DB::table('commit_reactions')->where('commit_id', $commit->id)->count())->toBe(5);
    $this->postJson(route('commit-star.store', $commit))->assertOk();
    $score = $commit->fresh()->hot_score;
    $this->deleteJson(route('commit-reaction.destroy', $commit))->assertOk()->assertJsonPath('stars_count', 1)->assertJsonCount(0, 'reactions');
    expect($commit->stars()->count())->toBe(1)->and($commit->fresh()->hot_score)->toBe($score)
        ->and($commit->user->notifications()->count())->toBe(1);
});

it('public reactions do not create stars or alter ranking', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->create();
    $score = $commit->hot_score;
    $this->actingAs($user)->postJson(route('commit-reaction.store', $commit), ['flavor' => '⭐'])->assertOk()->assertJsonPath('starred', false)->assertJsonPath('stars_count', 0);
    expect($commit->fresh()->hot_score)->toBe($score)->and($commit->user->notifications()->count())->toBe(0);
});
