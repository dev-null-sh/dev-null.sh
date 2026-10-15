<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Actions\DeleteUser;
use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;

it('restores only the deletion state within thirty seconds', function (CommitStatus $status): void {
    $parent = Commit::factory()->create();
    $commit = Commit::factory()->for($parent, 'parent')->create(['status' => $status]);
    $commit->stars()->attach($commit->user);
    $original = $commit->only(['body', 'status', 'parent_id', 'created_at']);

    $this->actingAs($commit->user)->delete(route('commit.destroy', $commit))->assertRedirect();
    $this->travel(29)->seconds();

    $this->fromRoute('log.show', Log::Everyone)
        ->post(route('commit-restore', $commit), ['status' => 'approved', 'body' => 'Changed'])
        ->assertRedirectToRoute('log.show', Log::Everyone)
        ->assertInertiaFlash('restored.id', $commit->id)
        ->assertInertiaFlash('restored.stars_count', 1)
        ->assertInertiaFlash('restored.starred', true);

    expect($commit->refresh()->only(array_keys($original)))->toEqual($original);
    $this->assertNotSoftDeleted($commit);
})->with(CommitStatus::cases());

it('flashes the server deadline from every deletion context', function (string $context): void {
    $commit = Commit::factory()->create();
    $from = match ($context) {
        'log' => route('log.show', Log::Everyone).'?cursor=older',
        'profile' => route('user.show', $commit->user).'?cursor=older',
        'thread' => route('commit.show', [$commit->user, Commit::factory()->for($commit->user)->create()]),
        'commit' => route('commit.show', [$commit->user, $commit]),
    };

    $response = $this->actingAs($commit->user)->from($from)->delete(route('commit.destroy', $commit));

    $response->assertRedirect($context === 'commit' ? route('user.show', $commit->user) : $from)
        ->assertInertiaFlash('deleted.id', $commit->id)
        ->assertInertiaFlash('deleted.expires_at', $commit->refresh()->deleted_at?->addSeconds(30)->toJSON())
        ->assertInertiaFlash('deleted.server_time', now()->toJSON());
})->with(['log', 'profile', 'thread', 'commit']);

it('rejects undo at and after the deadline', function (int $seconds): void {
    $this->travelTo(now()->startOfSecond());

    $commit = Commit::factory()->create();
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel($seconds)->seconds();

    $this->actingAs($commit->user)->post(route('commit-restore', $commit))
        ->assertSessionHasErrors('undo');

    $this->assertSoftDeleted($commit);
})->with([30, 31, 3600]);

it('requires authentication and ownership', function (): void {
    $commit = Commit::factory()->create();
    resolve(DeleteCommit::class)->handle($commit);

    $this->post(route('commit-restore', $commit))->assertRedirectToRoute('home');
    $this->actingAs(User::factory()->create())->post(route('commit-restore', $commit))->assertForbidden();

    $this->assertSoftDeleted($commit);
});

it('rejects an active commit and repeated undo', function (): void {
    $commit = Commit::factory()->create();
    $this->actingAs($commit->user)->post(route('commit-restore', $commit))->assertSessionHasErrors('undo');

    resolve(DeleteCommit::class)->handle($commit);
    $this->post(route('commit-restore', $commit))->assertSessionHasNoErrors();
    $this->post(route('commit-restore', $commit))->assertSessionHasErrors('undo');

    $this->assertNotSoftDeleted($commit);
});

it('cannot restore a force deleted commit', function (): void {
    $commit = Commit::factory()->create();
    $owner = $commit->user;
    $commit->delete();
    $commit->forceDelete();

    $this->actingAs($owner)->post(route('commit-restore', $commit))->assertNotFound();
});

it('cannot resurrect a commit erased by account deletion', function (): void {
    $owner = User::factory()->create();
    $parent = Commit::factory()->for($owner)->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $replyOwner = $reply->user;
    resolve(DeleteCommit::class)->handle($reply);
    resolve(DeleteUser::class)->handle($owner);

    $this->actingAs($replyOwner)->post(route('commit-restore', $reply))->assertNotFound();
    expect(Commit::withTrashed()->find($reply->id))->toBeNull();
});

it('keeps children and independently deleted descendants intact', function (bool $isReply): void {
    $commit = Commit::factory()->create(['parent_id' => $isReply ? Commit::factory()->create()->id : null]);
    $child = Commit::factory()->for($commit, 'parent')->create();
    $deletedChild = Commit::factory()->for($commit, 'parent')->create();
    $deletedChild->delete();

    resolve(DeleteCommit::class)->handle($commit);

    $this->actingAs($commit->user)->post(route('commit-restore', $commit))->assertSessionHasNoErrors();

    expect($child->refresh()->parent_id)->toBe($commit->id);
    $this->assertNotSoftDeleted($child);
    $this->assertSoftDeleted($deletedChild);
})->with([false, true]);

it('returns the actual visible parent count when undo replaces a tombstone', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    Commit::factory()->for($reply, 'parent')->create();
    Commit::factory()->for($parent, 'parent')->pending()->create();
    resolve(DeleteCommit::class)->handle($reply);

    $this->actingAs($reply->user)->post(route('commit-restore', $reply))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.parent.replies_count', 1);
});

it('keeps a deleted parent as a tombstone when restoring its reply', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    resolve(DeleteCommit::class)->handle($reply);
    resolve(DeleteCommit::class)->handle($parent);

    $this->actingAs($reply->user)->post(route('commit-restore', $reply))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.parent.id', $parent->id)
        ->assertInertiaFlashMissing('restored.parent.body');

    $this->assertSoftDeleted($parent);
    $this->assertNotSoftDeleted($reply);
});

it('keeps notification cleanup without sending notifications again', function (): void {
    $notification = Notification::factory()->replied()->create();
    $reply = $notification->commit;
    $childNotification = Notification::factory()->replied()->create(['commit_id' => Commit::factory()->for($reply, 'parent')->create()->id]);
    $unrelated = Notification::factory()->create();

    $this->actingAs($reply->user)->delete(route('commit.destroy', $reply))->assertRedirect();
    $this->post(route('commit-restore', $reply))->assertSessionHasNoErrors();

    $this->assertModelMissing($notification);
    $this->assertModelMissing($childNotification);
    expect(Notification::query()->pluck('id')->all())->toBe([$unrelated->id]);
});
