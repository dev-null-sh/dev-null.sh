<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\UpdateCommit;
use App\Enums\CommitStatus;
use App\Exceptions\CommitSourceChanged;
use App\Models\Commit;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    $this->travelTo(now()->startOfSecond());
});

it('refuses amendments while the author is in timeout', function (): void {
    $commit = Commit::factory()->create(['body' => 'Original']);
    UserTimeout::factory()->for($commit->user)->create();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Changed', 'expected_version' => $commit->version()])
        ->assertRedirect();

    expect($commit->refresh()->body)->toBe('Original');
});

it('permanently locks amendments after a reply or quote, even if it is deleted', function (bool $quote): void {
    $commit = Commit::factory()->create(['body' => 'Who has tested the new MacBook?']);
    $reply = resolve(CreateCommit::class)->handle(
        User::factory()->create(),
        'I have',
        parent: $quote ? null : $commit,
        quotedCommit: $quote ? $commit : null,
    );
    $reply->forceDelete();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Different question'])
        ->assertSessionHasErrors(['body' => 'This commit has received a reply or quote and can no longer be amended. Your changes were not saved.']);

    expect($commit->refresh()->body)->toBe('Who has tested the new MacBook?')
        ->and($commit->amendment_locked_at)->not->toBeNull()
        ->and($commit->revisions()->count())->toBe(0);
})->with([false, true]);

it('locks amendments for legacy replies and quotes without a recorded lock', function (bool $quote): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->create([$quote ? 'quoted_commit_id' : 'parent_id' => $commit->id]);
    $reply->delete();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Changed'])
        ->assertSessionHasErrors('body');
})->with([false, true]);

it('rejects an amendment submitted exactly at or after its deadline without creating history', function (int $seconds): void {
    $commit = Commit::factory()->create(['body' => 'Original']);
    $this->travel($seconds)->seconds();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Unsaved draft'])
        ->assertSessionHasErrors(['body' => 'The 15-minute amendment window has closed. Your changes were not saved.']);

    expect($commit->refresh()->body)->toBe('Original')
        ->and($commit->edited_at)->toBeNull()
        ->and($commit->revisions()->count())->toBe(0);
})->with([900, 901]);

it('allows an amendment just before the deadline but requires review', function (): void {
    $commit = Commit::factory()->create(['body' => 'Original']);
    $this->travel(899)->seconds();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['expected_version' => $commit->version(), 'body' => 'Original.'])
        ->assertSessionHasNoErrors();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});

it('replaces an amendment awaiting review instead of adding another version', function (): void {
    $commit = Commit::factory()->create(['body' => 'Original']);
    resolve(UpdateCommit::class)->handle($commit, 'First amendment');

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['expected_version' => $commit->refresh()->version(), 'body' => 'Second amendment'])
        ->assertSessionHasNoErrors();

    expect($commit->refresh()->body)->toBe('Second amendment')
        ->and($commit->status)->toBe(CommitStatus::Pending)
        ->and($commit->revisions()->pluck('body')->all())->toBe(['Original']);
});

it('lets a rejected commit be amended back into review', function (): void {
    $commit = Commit::factory()->rejected()->create(['body' => 'Rejected text']);

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['expected_version' => $commit->version(), 'body' => 'Fixed text'])
        ->assertSessionHasNoErrors();

    expect($commit->refresh()->body)->toBe('Fixed text')
        ->and($commit->status)->toBe(CommitStatus::Pending)
        ->and($commit->revisions()->count())->toBe(0);
});

it('lets whichever of approval and amendment comes first have its say', function (bool $approvalFirst): void {
    $admin = User::factory()->create(['username' => 'admin']);
    config(['app.admins' => ['admin']]);
    $commit = Commit::factory()->create(['body' => 'Original']);
    resolve(UpdateCommit::class)->handle($commit, 'First amendment');
    $reviewed = $commit->refresh()->version();

    $approve = fn () => $this->actingAs($admin)->post(route('commit-approval.store', $commit), ['expected_version' => $reviewed]);
    $amend = fn () => $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['expected_version' => $reviewed, 'body' => 'Second amendment']);

    if ($approvalFirst) {
        $approve()->assertSessionHasNoErrors();
        $amend()->assertSessionHasNoErrors();

        expect($commit->refresh()->body)->toBe('Second amendment')
            ->and($commit->revisions()->pluck('body')->all())->toContain('First amendment');
    } else {
        $amend()->assertSessionHasNoErrors();
        $approve()->assertSessionHasErrors('body');

        expect($commit->refresh()->body)->toBe('Second amendment')
            ->and($commit->status)->toBe(CommitStatus::Pending)
            ->and($commit->revisions()->pluck('body')->all())->not->toContain('First amendment');
    }
})->with(['approval first' => true, 'amendment first' => false]);

it('rejects stale amendment drafts after an intervening approved amendment', function (): void {
    $commit = Commit::factory()->create(['body' => 'Original']);
    resolve(UpdateCommit::class)->handle($commit, 'First amendment');
    $commit->refresh()->forceFill(['status' => CommitStatus::Approved])->save();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Stale draft', 'expected_version' => $commit->id])
        ->assertSessionHasErrors('body');

    expect($commit->refresh()->body)->toBe('First amendment');
});

it('does not allow amendments to change attachments or relationships', function (string $field): void {
    $commit = Commit::factory()->create(['body' => 'Original']);

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Original.', $field => 'changed'])
        ->assertSessionHasErrors($field);

    expect($commit->refresh()->body)->toBe('Original');
})->with(['images', 'image_descriptions', 'poll', 'parent_id', 'quoted_commit_id']);

it('rejects stale interaction snapshots if an amendment won the race', function (bool $quote): void {
    $commit = Commit::factory()->create(['body' => 'Original']);
    $stale = $commit->fresh();
    resolve(UpdateCommit::class)->handle($commit, 'Amended');
    $commit->refresh()->forceFill(['status' => CommitStatus::Approved])->save();

    expect(fn () => resolve(CreateCommit::class)->handle(
        User::factory()->create(), 'A reply', parent: $quote ? null : $stale, quotedCommit: $quote ? $stale : null,
    ))->toThrow(CommitSourceChanged::class);

    expect($commit->replies()->count())->toBe(0)
        ->and($commit->quotes()->count())->toBe(0)
        ->and($commit->refresh()->amendment_locked_at)->toBeNull();
})->with([false, true]);

it('requires a matching revision token to reply to an amended commit', function (): void {
    $commit = Commit::factory()->create(['body' => 'Original']);
    resolve(UpdateCommit::class)->handle($commit, 'Amended');
    $commit->refresh()->forceFill(['status' => CommitStatus::Approved])->save();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('commit-reply.store', $commit), ['body' => 'A reply'])
        ->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->postJson(route('commit-reply.store', $commit), ['body' => 'A reply', 'source_version' => $commit->id])
        ->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->postJson(route('commit-reply.store', $commit), ['body' => 'A reply', 'source_version' => $commit->version()])
        ->assertSuccessful();

    expect($commit->refresh()->amendment_locked_at)->not->toBeNull();
});

it('rejects an old source version even when the text matches again', function (): void {
    $commit = Commit::factory()->create(['body' => 'Original']);
    $version = $commit->version();
    $commit->update(['revision_id' => Str::uuid7()->toString(), 'edited_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('commit-reply.store', $commit), ['body' => 'Reply', 'source_version' => $version])
        ->assertUnprocessable()->assertJsonValidationErrors('body');

    expect($commit->replies()->count())->toBe(0);
});

it('requires an explicit current token for every amendment', function (): void {
    $commit = Commit::factory()->create(['body' => 'Original']);
    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Changed'])
        ->assertSessionHasErrors('body');
    expect($commit->refresh()->body)->toBe('Original');
});

it('rejects stale tokens even for two amendments within the same second', function (): void {
    $user = User::factory()->create();
    config(['app.admins' => [$user->username]]);
    $commit = Commit::factory()->for($user)->create(['body' => 'Original']);
    $this->actingAs($user);
    $first = $commit->version();
    $this->patch(route('commit.update', $commit), ['body' => 'Original.', 'expected_version' => $first])->assertSessionHasNoErrors();
    $second = $commit->refresh()->version();
    $this->patch(route('commit.update', $commit), ['body' => 'Original', 'expected_version' => $second])->assertSessionHasNoErrors();
    $this->patch(route('commit.update', $commit), ['body' => 'Stale', 'expected_version' => $first])->assertSessionHasErrors('body');
    expect($commit->refresh()->body)->toBe('Original')->and($commit->version())->not->toBe($first)->not->toBe($second);
});
