<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\LinkPreview;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserTimeout;
use App\Services\CommitTranslations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

it('requires sign in to edit a commit', function (): void {
    $commit = Commit::factory()->create();

    $this->patch(route('commit.update', $commit), ['body' => 'Updated text'])->assertRedirectToRoute('home');

    expect($commit->fresh()->body)->toBe($commit->body);
});

it('forbids editing another author commit even as an admin', function (): void {
    $commit = Commit::factory()->create();
    $user = User::factory()->create(['username' => 'admin']);
    config(['app.admins' => ['admin']]);

    $this->actingAs($user)->patch(route('commit.update', $commit), ['body' => 'Changed'])->assertForbidden();

    expect($commit->fresh()->body)->toBe($commit->body);
});

it('edits own text without changing authorship or creation time', function (CommitStatus $status): void {
    Queue::fake();
    $commit = Commit::factory()->create(['status' => $status, 'ai_decision' => CommitStatus::Approved]);
    $original = $commit->getAttributes();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), [
        'body' => "  Updated text\n\n\nwith a correction  ",
        'user_id' => User::factory()->create()->id,
        'status' => 'approved',
    ])->assertRedirect();

    expect($commit->fresh()->body)->toBe("Updated text\n\nwith a correction")
        ->and($commit->fresh()->status)->toBe(CommitStatus::Pending)
        ->and($commit->fresh()->ai_decision)->toBeNull()
        ->and($commit->fresh()->ai_categories)->toBeNull()
        ->and($commit->fresh()->embedding)->toBeNull()
        ->and($commit->fresh()->user_id)->toBe($original['user_id'])
        ->and($commit->fresh()->created_at->toJSON())->toBe($commit->created_at->toJSON());
})->with(CommitStatus::cases());

it('validates edited text', function (mixed $body): void {
    $commit = Commit::factory()->create();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => $body])
        ->assertSessionHasErrors('body');

    expect($commit->fresh()->body)->toBe($commit->body);
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'too long' => [str_repeat('a', 4001)],
    'array' => [['invalid']],
]);

it('does not edit missing or deleted commits', function (): void {
    $commit = Commit::factory()->create();
    $commit->delete();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Changed'])->assertNotFound();
    $this->patch('/commits/missing', ['body' => 'Changed'])->assertNotFound();
});

it('queues moderation and embedding for changed text', function (): void {
    Queue::fake();
    config(['services.openai.key' => 'test-key']);
    $commit = Commit::factory()->create();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Corrected text'])->assertRedirect();

    Queue::assertPushed(ModerateCommit::class);
    Queue::assertPushed(EmbedCommit::class);
});

it('leaves unchanged text alone', function (): void {
    Queue::fake();
    $commit = Commit::factory()->create(['body' => 'Same text', 'status' => CommitStatus::Approved]);

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Same text'])->assertRedirect();

    expect($commit->fresh()->status)->toBe(CommitStatus::Approved);
    Queue::assertNothingPushed();
});

it('keeps admin edits approved', function (): void {
    Queue::fake();
    $user = User::factory()->create(['username' => 'admin']);
    config(['app.admins' => ['admin']]);
    $commit = Commit::factory()->for($user)->create();

    $this->actingAs($user)->patch(route('commit.update', $commit), ['body' => 'Corrected'])->assertRedirect();

    expect($commit->fresh()->status)->toBe(CommitStatus::Approved);
});

it('preserves images and allows their text to be cleared', function (): void {
    Queue::fake();
    $commit = Commit::factory()->create();
    $image = CommitImage::factory()->for($commit)->create();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => ''])->assertRedirect();

    expect($commit->fresh()->body)->toBe('')
        ->and($commit->fresh()->images->sole()->id)->toBe($image->id);
});

it('keeps thread relationships and stars when edited', function (): void {
    Queue::fake();
    $parent = Commit::factory()->create();
    $commit = Commit::factory()->for($parent, 'parent')->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();
    $reader = User::factory()->create();
    $commit->stars()->attach($reader);

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Corrected reply'])->assertRedirect();

    expect($commit->fresh()->parent_id)->toBe($parent->id)
        ->and($reply->fresh()->parent_id)->toBe($commit->id)
        ->and($commit->fresh()->stars->sole()->id)->toBe($reader->id);
});

it('clears old translations and replaces a removed link preview', function (): void {
    Queue::fake();
    $preview = LinkPreview::factory()->create(['url' => 'https://example.com/old']);
    $commit = Commit::factory()->for($preview, 'linkPreview')->create(['body' => 'https://example.com/old']);
    $key = resolve(CommitTranslations::class)->key($commit);
    Cache::put($key, ['old']);

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'https://example.com/new'])->assertRedirect();

    expect(Cache::has($key))->toBeFalse()
        ->and($commit->fresh()->linkPreview->url)->toBe('https://example.com/new');
    Queue::assertPushed(UnfurlLink::class);
});

it('retains an existing preview if its link remains', function (): void {
    Queue::fake();
    $preview = LinkPreview::factory()->create(['url' => 'https://example.com/kept', 'fetched_at' => now()]);
    $commit = Commit::factory()->for($preview, 'linkPreview')->create();

    $this->actingAs($commit->user)->patch(route('commit.update', $commit), ['body' => 'Updated https://example.com/kept'])->assertRedirect();

    expect($commit->fresh()->link_preview_id)->toBe($preview->id);
    Queue::assertNotPushed(UnfurlLink::class);
});

it('removes notifications for removed mentions and notifies new mentions on approval', function (): void {
    Queue::fake();
    $author = User::factory()->create(['username' => 'admin']);
    config(['app.admins' => ['admin']]);
    $old = User::factory()->create(['username' => 'old-mention']);
    $new = User::factory()->create(['username' => 'new-mention']);
    $commit = Commit::factory()->for($author)->create();
    $notification = Notification::factory()->create([
        'actor_id' => $author->id,
        'recipient_id' => $old->id,
        'commit_id' => $commit->id,
        'type' => NotificationType::Mentioned,
    ]);

    $this->actingAs($author)->patch(route('commit.update', $commit), ['body' => 'Thanks @'.$new->username])->assertRedirect();

    expect($notification->fresh())->toBeNull()
        ->and($commit->fresh()->mentions)->toContain($new->id)
        ->and(Notification::query()->where('commit_id', $commit->id)->where('recipient_id', $new->id)->exists())->toBeTrue();
});

it('enforces posting timeouts when editing', function (): void {
    $commit = Commit::factory()->create();
    UserTimeout::factory()->create(['github_id' => $commit->user->github_id]);

    $this->actingAs($commit->user)->patchJson(route('commit.update', $commit), ['body' => 'Changed'])->assertForbidden();

    expect($commit->fresh()->body)->toBe($commit->body);
});

it('limits repeated edits', function (): void {
    Queue::fake();
    $commit = Commit::factory()->create();
    $this->actingAs($commit->user);

    foreach (range(1, 10) as $number) {
        $this->patchJson(route('commit.update', $commit), ['body' => 'Correction '.$number])->assertRedirect();
    }

    $this->patchJson(route('commit.update', $commit), ['body' => 'Too many'])->assertTooManyRequests();
    expect($commit->fresh()->body)->toBe('Correction 10');
});
