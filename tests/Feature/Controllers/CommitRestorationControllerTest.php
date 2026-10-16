<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Actions\DeleteUser;
use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Models\Bookmark;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('restores only the deletion state within thirty seconds', function (CommitStatus $status): void {
    $parent = Commit::factory()->create();
    $commit = Commit::factory()->for($parent, 'parent')->create(['status' => $status]);
    $commit->stars()->attach($commit->user);
    $bookmark = Bookmark::factory()->create(['user_id' => $commit->user_id, 'commit_id' => $commit->id]);
    $original = $commit->only(['body', 'status', 'parent_id', 'created_at']);

    $this->actingAs($commit->user)->delete(route('commit.destroy', $commit))->assertRedirect();
    $this->travel(29)->seconds();

    $this->fromRoute('log.show', Log::Everyone)
        ->post(route('commit-restoration.store', $commit), ['status' => 'approved', 'body' => 'Changed'])
        ->assertRedirectToRoute('log.show', Log::Everyone)
        ->assertInertiaFlash('restored.id', $commit->id)
        ->assertInertiaFlash('restored.stars_count', 1)
        ->assertInertiaFlash('restored.starred', true)
        ->assertInertiaFlash('restored.is_saved', true)
        ->assertInertiaFlash('restored.saved_order', $bookmark->id);

    expect($commit->refresh()->only(array_keys($original)))->toEqual($original);
    $this->assertNotSoftDeleted($commit);
})->with(CommitStatus::cases());

it('restores a commit with its images', function (): void {
    Storage::fake();

    $image = CommitImage::factory()->create();
    Storage::disk()->put($image->path, 'image');
    $commit = $image->commit;

    $this->actingAs($commit->user)->delete(route('commit.destroy', $commit))->assertRedirect();
    $this->get(route('commit-image.show', $image))->assertNotFound();

    $this->post(route('commit-restoration.store', $commit))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.images.0.id', $image->id);

    $this->get(route('commit-image.show', $image))->assertOk();
});

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

    $this->actingAs($commit->user)->post(route('commit-restoration.store', $commit))
        ->assertSessionHasErrors('undo');

    $this->assertSoftDeleted($commit);
})->with([30, 31, 3600]);

it('requires authentication and ownership', function (): void {
    $commit = Commit::factory()->create();
    resolve(DeleteCommit::class)->handle($commit);

    $this->post(route('commit-restoration.store', $commit))->assertRedirectToRoute('home');
    $this->actingAs(User::factory()->create())->post(route('commit-restoration.store', $commit))->assertForbidden();

    $this->assertSoftDeleted($commit);
});

it('rejects an active commit and repeated undo', function (): void {
    $commit = Commit::factory()->create();
    $this->actingAs($commit->user)->post(route('commit-restoration.store', $commit))->assertSessionHasErrors('undo');

    resolve(DeleteCommit::class)->handle($commit);
    $this->post(route('commit-restoration.store', $commit))->assertSessionHasNoErrors();
    $this->post(route('commit-restoration.store', $commit))->assertSessionHasErrors('undo');

    $this->assertNotSoftDeleted($commit);
});

it('cannot restore a force deleted commit', function (): void {
    $commit = Commit::factory()->create();
    $owner = $commit->user;
    $commit->delete();
    $commit->forceDelete();

    $this->actingAs($owner)->post(route('commit-restoration.store', $commit))->assertNotFound();
});

it('cannot resurrect a commit erased by account deletion', function (): void {
    $owner = User::factory()->create();
    $parent = Commit::factory()->for($owner)->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $replyOwner = $reply->user;
    resolve(DeleteCommit::class)->handle($reply);
    resolve(DeleteUser::class)->handle($owner);

    $this->actingAs($replyOwner)->post(route('commit-restoration.store', $reply))->assertNotFound();
    expect(Commit::withTrashed()->find($reply->id))->toBeNull();
});

it('keeps children and independently deleted descendants intact', function (bool $isReply): void {
    $commit = Commit::factory()->create(['parent_id' => $isReply ? Commit::factory()->create()->id : null]);
    $child = Commit::factory()->for($commit, 'parent')->create();
    $deletedChild = Commit::factory()->for($commit, 'parent')->create();
    $deletedChild->delete();

    resolve(DeleteCommit::class)->handle($commit);

    $this->actingAs($commit->user)->post(route('commit-restoration.store', $commit))->assertSessionHasNoErrors();

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

    $this->actingAs($reply->user)->post(route('commit-restoration.store', $reply))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.parent.replies_count', 1);
});

it('keeps a deleted parent as a tombstone when restoring its reply', function (): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    resolve(DeleteCommit::class)->handle($reply);
    resolve(DeleteCommit::class)->handle($parent);

    $this->actingAs($reply->user)->post(route('commit-restoration.store', $reply))
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
    $this->post(route('commit-restoration.store', $reply))->assertSessionHasNoErrors();

    $this->assertModelMissing($notification);
    $this->assertModelMissing($childNotification);
    expect(Notification::query()->pluck('id')->all())->toBe([$unrelated->id]);
});

it('flags a restored profile pin so the feed keeps it out of the list', function (bool $isPinned): void {
    $commit = Commit::factory()->create();
    $commit->user->update(['pinned_commit_id' => $isPinned ? $commit->id : Commit::factory()->for($commit->user)->create()->id]);

    resolve(DeleteCommit::class)->handle($commit);

    $this->actingAs($commit->user->refresh())->fromRoute('user.show', $commit->user)
        ->post(route('commit-restoration.store', $commit))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.is_pinned', $isPinned);

    $this->get(route('user.show', $commit->user))
        ->assertInertia(fn (Assert $page): Assert => $isPinned
            ? $page->where('pinnedCommit.id', $commit->id)->has('commits.data', 0)
            : $page->has('commits.data', 1)->where('commits.data.0.id', $commit->id));
})->with([true, false]);
