<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Actions\DeleteUser;
use App\Enums\CommitStatus;
use App\Enums\Log;
use App\Enums\NotificationType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('restores only the deletion state within thirty seconds', function (CommitStatus $status): void {
    $parent = Commit::factory()->create();
    $commit = Commit::factory()->for($parent, 'parent')->create(['status' => $status]);
    $commit->stars()->attach($commit->user);
    $original = $commit->only(['body', 'status', 'parent_id', 'created_at']);

    $this->actingAs($commit->user)->delete(route('commit.destroy', $commit))->assertRedirect();
    $this->travel(29)->seconds();

    $this->fromRoute('log.show', Log::Trending)
        ->post(route('commit-restoration.store', $commit), ['status' => 'approved', 'body' => 'Changed'])
        ->assertRedirectToRoute('log.show', Log::Trending)
        ->assertInertiaFlash('restored.id', $commit->id)
        ->assertInertiaFlash('restored.stars_count', 1)
        ->assertInertiaFlash('restored.starred', true)
        ->assertInertiaFlash('restored.starred_order', $commit->stars()->newPivotQuery()->where('user_id', $commit->user_id)->value('created_at'));

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
        'log' => route('log.show', Log::Trending).'?cursor=older',
        'profile' => route('user.show', $commit->user).'?cursor=older',
        'thread' => route('commit.show', [$commit->user, Commit::factory()->for($commit->user)->create()]),
        'commit' => route('commit.show', [$commit->user, $commit]),
        'photo' => route('commit-photo.show', [$commit->user, $commit, 1]),
    };

    $response = $this->actingAs($commit->user)->from($from)->delete(route('commit.destroy', $commit));

    $response->assertRedirect(in_array($context, ['commit', 'photo'], true) ? route('user.show', $commit->user) : $from)
        ->assertInertiaFlash('deleted.id', $commit->id)
        ->assertInertiaFlash('deleted.parent_id', $commit->parent_id)
        ->assertInertiaFlash('deleted.deleted_at', $commit->refresh()->deleted_at?->toJSON())
        ->assertInertiaFlash('deleted.expires_at', $commit->refresh()->deleted_at?->addSeconds(30)->toJSON())
        ->assertInertiaFlash('deleted.server_time', now()->toJSON());
})->with(['log', 'profile', 'thread', 'commit', 'photo']);

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

it('leaves the restored next commit of a thread out of its parent count', function (): void {
    $parent = Commit::factory()->create();
    $next = Commit::factory()->for($parent, 'parent')->for($parent->user)->create();
    Commit::factory()->for($parent, 'parent')->create();
    resolve(DeleteCommit::class)->handle($next);

    $this->actingAs($next->user)->post(route('commit-restoration.store', $next))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.parent.replies_count', 1)
        ->assertInertiaFlash('restored.parent.continuation_id', $next->id);
});

it('leaves the next commit of a thread out of the count of a restored quote', function (): void {
    $quoted = Commit::factory()->create();
    Commit::factory()->for($quoted, 'parent')->for($quoted->user)->create();
    Commit::factory()->for($quoted, 'parent')->create();
    $quote = Commit::factory()->for($quoted, 'quotedCommit')->create();
    resolve(DeleteCommit::class)->handle($quote);

    $this->actingAs($quote->user)->post(route('commit-restoration.store', $quote))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.quoted_commit.replies_count', 2);
});

it('counts every reply of a deleted parent when restoring one of them', function (): void {
    $parent = Commit::factory()->create();
    Commit::factory()->for($parent, 'parent')->for($parent->user)->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    resolve(DeleteCommit::class)->handle($reply);
    resolve(DeleteCommit::class)->handle($parent);

    $this->actingAs($reply->user)->post(route('commit-restoration.store', $reply))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.parent.replies_count', 2);
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

it('keeps public api and feed visibility consistent through deletion and undo', function (CommitStatus $status): void {
    $commit = Commit::factory()->create(['status' => $status, 'body' => 'Commit restored through undo']);
    $user = $commit->user;

    $this->actingAs($user)->delete(route('commit.destroy', $commit))->assertRedirect();

    $this->getJson(route('api-commit.show', $commit))->assertNotFound();
    $this->getJson(route('api-user-commit.index', $user))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->get(route('user-feed.show', $user))->assertOk()->assertDontSee($commit->body);

    $this->post(route('commit-restoration.store', $commit))->assertSessionHasNoErrors();

    if ($status === CommitStatus::Approved) {
        $this->getJson(route('api-commit.show', $commit))
            ->assertOk()
            ->assertJsonPath('data.id', $commit->id);
        $this->getJson(route('api-user-commit.index', $user))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$commit->id]);
        $this->get(route('user-feed.show', $user))->assertOk()->assertSee($commit->body);
    } else {
        $this->getJson(route('api-commit.show', $commit))->assertNotFound();
        $this->getJson(route('api-user-commit.index', $user))
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->get(route('user-feed.show', $user))->assertOk()->assertDontSee($commit->body);
    }
})->with(CommitStatus::cases());

it('restores a reply beneath an authorless account deletion tombstone', function (): void {
    $root = Commit::factory()->create();
    $parent = Commit::factory()->for($root, 'parent')->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $owner = $reply->user;
    resolve(DeleteCommit::class)->handle($reply);
    resolve(DeleteUser::class)->handle($parent->user);

    $this->actingAs($owner)->post(route('commit-restoration.store', $reply))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.parent.id', $parent->id)
        ->assertInertiaFlashMissing('restored.parent.body')
        ->assertInertiaFlashMissing('restored.parent.user_id')
        ->assertInertiaFlashMissing('restored.parent.user');

    $this->assertSoftDeleted($parent);
    $this->assertNotSoftDeleted($reply);
    expect($parent->refresh()->user_id)->toBeNull();
});

it('keeps a blocked parent out of the restoration payload', function (bool $ownerBlocks): void {
    $parent = Commit::factory()->create();
    $reply = Commit::factory()->for($parent, 'parent')->create();
    $owner = $reply->user;
    resolve(DeleteCommit::class)->handle($reply);

    $ownerBlocks ? $owner->blocking()->attach($parent->user) : $parent->user->blocking()->attach($owner);

    $this->actingAs($owner)->post(route('commit-restoration.store', $reply))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.id', $reply->id)
        ->assertInertiaFlash('restored.parent', null);
})->with([true, false]);

it('counts only visible replies after undo', function (bool $muting): void {
    $commit = Commit::factory()->create();
    $owner = $commit->user;
    Commit::factory()->for($commit, 'parent')->create();
    $hidden = Commit::factory()->for($commit, 'parent')->create();
    resolve(DeleteCommit::class)->handle($commit);

    $muting ? $owner->muting()->attach($hidden->user) : $hidden->user->blocking()->attach($owner);

    $this->actingAs($owner)->post(route('commit-restoration.store', $commit))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.replies_count', 1);
})->with([true, false]);

it('retains the chosen link card through deletion and undo without queuing it again', function (bool $hasCard): void {
    $user = User::factory()->create();
    Queue::fake([UnfurlLink::class]);
    $url = 'https://example.com/selected';

    $this->actingAs($user)->post(route('commit.store'), [
        'body' => "https://example.com/first {$url}",
        'link_preview_url' => $hasCard ? $url : '',
    ])->assertSessionHasNoErrors();

    $commit = $user->commits()->sole();
    $previewId = $commit->link_preview_id;
    $this->delete(route('commit.destroy', $commit))->assertRedirect();
    $response = $this->post(route('commit-restoration.store', $commit))->assertSessionHasNoErrors();

    expect($commit->refresh()->link_preview_id)->toBe($previewId);

    if ($hasCard) {
        $response->assertInertiaFlash('restored.link_preview.url', $url);
        Queue::assertPushed(UnfurlLink::class, 1);
        Queue::assertPushed(UnfurlLink::class, fn (UnfurlLink $job): bool => $job->linkPreview->is($commit->linkPreview));
    } else {
        $response->assertInertiaFlash('restored.link_preview', null);
        Queue::assertNotPushed(UnfurlLink::class);
    }
})->with([true, false]);

it('restores a quote with only the quoted content its author can still see', function (string $state): void {
    $target = Commit::factory()->create();
    $quote = Commit::factory()->for($target, 'quotedCommit')->create();
    $owner = $quote->user;
    resolve(DeleteCommit::class)->handle($quote);

    match ($state) {
        'pending' => $target->update(['status' => CommitStatus::Pending]),
        'rejected' => $target->update(['status' => CommitStatus::Rejected]),
        'deleted' => resolve(DeleteCommit::class)->handle($target),
        'blocked' => $target->user->blocking()->attach($owner),
        default => null,
    };

    $response = $this->actingAs($owner)->post(route('commit-restoration.store', $quote))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.quoted_commit_id', $target->id);

    if ($state === 'approved') {
        $response->assertInertiaFlash('restored.quoted_commit.id', $target->id)
            ->assertInertiaFlash('restored.quoted_commit.body', $target->body);
    } elseif ($state === 'deleted') {
        $response->assertInertiaFlash('restored.quoted_commit.id', $target->id)
            ->assertInertiaFlashMissing('restored.quoted_commit.body')
            ->assertInertiaFlashMissing('restored.quoted_commit.user');
    } else {
        $response->assertInertiaFlash('restored.quoted_commit', null);
    }
})->with(['approved', 'pending', 'rejected', 'deleted', 'blocked']);

it('removes quote notifications permanently when their target is deleted and restored', function (): void {
    $notification = Notification::factory()->quoted()->create();
    $quote = $notification->commit;
    $target = $quote->quotedCommit;

    $this->actingAs($target->user)->delete(route('commit.destroy', $target))->assertRedirect();
    $this->assertModelMissing($notification);
    $this->assertNotSoftDeleted($quote);

    $this->post(route('commit-restoration.store', $target))->assertSessionHasNoErrors();

    expect(Notification::query()->count())->toBe(0);
    $this->assertNotSoftDeleted($target);
});

it('keeps rejection notifications removed and moderation hints private after undo', function (): void {
    $commit = Commit::factory()->rejected()->create([
        'ai_decision' => CommitStatus::Rejected,
        'ai_categories' => ['harassment'],
    ]);
    Notification::factory()->create([
        'commit_id' => $commit->id,
        'actor_id' => $commit->user_id,
        'recipient_id' => $commit->user_id,
        'type' => NotificationType::Rejected,
    ]);

    $this->actingAs($commit->user)->delete(route('commit.destroy', $commit))->assertRedirect();
    $this->post(route('commit-restoration.store', $commit))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.status', 'rejected')
        ->assertInertiaFlashMissing('restored.ai_decision')
        ->assertInertiaFlashMissing('restored.ai_categories');

    expect(Notification::query()->count())->toBe(0)
        ->and($commit->refresh()->ai_decision)->toBe(CommitStatus::Rejected)
        ->and($commit->ai_categories)->toBe(['harassment']);
});

it('refreshes profile achievement totals through deletion and undo', function (): void {
    $commit = Commit::factory()->create();
    $user = $commit->user;
    $this->actingAs($user)->fromRoute('user.show', $user)
        ->delete(route('commit.destroy', $commit))->assertRedirect();

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'user/show',
        'X-Inertia-Partial-Data' => 'achievements',
    ];
    $this->get(route('user.show', $user), $headers)
        ->assertOk()
        ->assertJsonPath('props.achievements.xp_breakdown.commits.count', 0);

    $this->post(route('commit-restoration.store', $commit))->assertSessionHasNoErrors();
    $this->get(route('user.show', $user), $headers)
        ->assertOk()
        ->assertJsonPath('props.achievements.xp_breakdown.commits.count', 1);
});

it('returns the quote target reply count after restoring a quote', function (): void {
    $target = Commit::factory()->create();
    $quote = Commit::factory()->for($target, 'quotedCommit')->create();
    resolve(DeleteCommit::class)->handle($quote);

    $this->actingAs($quote->user)->post(route('commit-restoration.store', $quote))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('restored.quoted_commit.replies_count', 1);

    $this->get(route('commit.show', [$target->user, $target]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('commit.replies_count', 1)
            ->where('replies.data.0.id', $quote->id));
});
