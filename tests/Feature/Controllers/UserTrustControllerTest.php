<?php

declare(strict_types=1);

use App\Actions\UpdateUserTrust;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Models\UserTrustChange;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

it('lets an administrator grant and revoke trust with a decision history', function (): void {
    $moderator = User::factory()->create();
    $author = User::factory()->create();
    config(['app.admins' => [$moderator->username]]);

    $this->actingAs($moderator)->from(route('user.show', $author))
        ->put(route('user-trust.update', $author), ['is_trusted' => true])
        ->assertRedirectToRoute('user.show', $author);

    expect($author->fresh()->is_trusted)->toBeTrue();
    $grant = UserTrustChange::query()->sole();
    expect($grant)->user_id->toBe($author->id)
        ->actor_id->toBe($moderator->id)
        ->is_trusted->toBeTrue();

    $this->put(route('user-trust.update', $author), ['is_trusted' => true])->assertRedirect();
    $this->assertDatabaseCount('user_trust_changes', 1);

    $this->put(route('user-trust.update', $author), ['is_trusted' => false])->assertRedirect();

    expect($author->fresh()->is_trusted)->toBeFalse();
    $this->assertDatabaseCount('user_trust_changes', 2);
    $revoke = UserTrustChange::query()->latest('id')->firstOrFail();
    expect($revoke)->actor_id->toBe($moderator->id)->is_trusted->toBeFalse();

    $this->get(route('user.show', $author))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('moderation.isTrusted', false)
            ->where('moderation.isAdmin', false)
            ->where('moderation.history.0.id', $revoke->id)
            ->where('moderation.history.0.actor.username', $moderator->username)
            ->where('moderation.history.1.id', $grant->id));
});

it('requires authentication to change trust', function (): void {
    $author = User::factory()->create();

    $this->put(route('user-trust.update', $author), ['is_trusted' => true])
        ->assertRedirectToRoute('home');

    expect($author->fresh()->is_trusted)->toBeFalse();
    $this->assertDatabaseCount('user_trust_changes', 0);
});

it('does not let ordinary or trusted authors grant themselves trust', function (bool $trusted): void {
    $author = User::factory()->create(['is_trusted' => $trusted]);

    $this->actingAs($author)->put(route('user-trust.update', $author), ['is_trusted' => ! $trusted])
        ->assertForbidden();

    expect($author->fresh()->is_trusted)->toBe($trusted);
    $this->assertDatabaseCount('user_trust_changes', 0);
})->with(['ordinary' => false, 'trusted' => true]);

it('does not let an ordinary author change another authors trust', function (): void {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($viewer)->put(route('user-trust.update', $author), ['is_trusted' => true])
        ->assertForbidden();

    expect($author->fresh()->is_trusted)->toBeFalse();
    $this->assertDatabaseCount('user_trust_changes', 0);
});

it('validates trust changes without writing anything', function (array $input, string $message): void {
    $moderator = User::factory()->create();
    $author = User::factory()->create();
    config(['app.admins' => [$moderator->username]]);

    $this->actingAs($moderator)->put(route('user-trust.update', $author), $input)
        ->assertSessionHasErrors(['is_trusted' => $message]);

    expect($author->fresh()->is_trusted)->toBeFalse();
    $this->assertDatabaseCount('user_trust_changes', 0);
})->with([
    'missing' => [[], 'The is trusted field is required.'],
    'invalid boolean' => [['is_trusted' => 'trusted'], 'The is trusted field must be true or false.'],
]);

it('keeps administrator publishing access independent of trust', function (): void {
    $moderator = User::factory()->create();
    $administrator = User::factory()->create();
    config(['app.admins' => [$moderator->username, $administrator->username]]);

    $this->actingAs($moderator)->put(route('user-trust.update', $administrator), ['is_trusted' => true])
        ->assertForbidden();

    $this->get(route('user.show', $administrator))->assertOk()
        ->assertInertia(fn ($page) => $page->where('moderation.isAdmin', true));
    $this->assertDatabaseCount('user_trust_changes', 0);
});

it('keeps trust and its history private from other readers and the author', function (): void {
    $author = User::factory()->create(['is_trusted' => true]);
    $moderator = User::factory()->create();
    UserTrustChange::factory()->for($author)->for($moderator, 'actor')->create();

    foreach ([$author, User::factory()->create()] as $viewer) {
        $this->actingAs($viewer)->get(route('user.show', $author))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('moderation', null)
                ->missing('user.is_trusted')
                ->missing('auth.user.is_trusted'));
    }

    $this->get(route('user-preview.show', $author))->assertOk()->assertJsonMissingPath('is_trusted');
});

it('never changes existing commit decisions when trust changes', function (): void {
    $moderator = User::factory()->create();
    $author = User::factory()->create();
    config(['app.admins' => [$moderator->username]]);
    $pending = Commit::factory()->for($author)->pending()->create();
    $approved = Commit::factory()->for($author)->create();
    $rejected = Commit::factory()->for($author)->rejected()->create();

    foreach ([true, false] as $trusted) {
        $this->actingAs($moderator)->put(route('user-trust.update', $author), ['is_trusted' => $trusted])
            ->assertRedirect();

        expect($pending->fresh()->status)->toBe(CommitStatus::Pending);
        expect($approved->fresh()->status)->toBe(CommitStatus::Approved);
        expect($rejected->fresh()->status)->toBe(CommitStatus::Rejected);
    }
});

it('publishes trusted web replies and notifies their readers without granting admin access', function (): void {
    $author = User::factory()->trusted()->create();
    $parent = Commit::factory()->create();
    $mentioned = User::factory()->create(['username' => 'taylorotwell']);

    $this->actingAs($author)->post(route('commit.store'), [
        'body' => 'Fixed this with @'.$mentioned->username,
        'parent_id' => $parent->id,
        'is_trusted' => false,
    ])->assertRedirect();

    $reply = $author->commits()->sole();
    expect($reply)->status->toBe(CommitStatus::Approved)->parent_id->toBe($parent->id);
    expect($parent->user->notifications()->sole()->commit_id)->toBe($reply->id);
    expect($mentioned->notifications()->sole()->commit_id)->toBe($reply->id);
    expect($author->fresh()->is_trusted)->toBeTrue();
    $this->get(route('moderation.show'))->assertForbidden();
    $this->assertDatabaseCount('user_trust_changes', 0);
});

it('ignores attempts to gain trust by submitting a commit', function (): void {
    $author = User::factory()->create();

    $this->actingAs($author)->post(route('commit.store'), [
        'body' => 'A normal commit',
        'is_trusted' => true,
        'status' => 'approved',
    ])->assertRedirect();

    expect($author->fresh()->is_trusted)->toBeFalse();
    expect($author->commits()->sole()->status)->toBe(CommitStatus::Pending);
});

it('rolls back trust if recording the decision fails', function (): void {
    $author = User::factory()->create();
    $missingModerator = User::factory()->make(['id' => Str::uuid7()->toString()]);

    expect(fn () => resolve(UpdateUserTrust::class)->handle($author, $missingModerator, true))
        ->toThrow(QueryException::class);

    expect($author->fresh()->is_trusted)->toBeFalse();
    $this->assertDatabaseCount('user_trust_changes', 0);
});

it('preserves history when a moderator is deleted and removes it with its author', function (): void {
    $moderator = User::factory()->create();
    $author = User::factory()->create();
    $viewer = User::factory()->create();
    config(['app.admins' => [$viewer->username]]);
    resolve(UpdateUserTrust::class)->handle($author, $moderator, true);
    $moderator->delete();

    $this->actingAs($viewer)->get(route('user.show', $author))->assertOk()
        ->assertInertia(fn ($page) => $page->where('moderation.history.0.actor', null));

    $author->delete();
    $this->assertDatabaseCount('user_trust_changes', 0);
});
