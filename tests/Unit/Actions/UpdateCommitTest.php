<?php

declare(strict_types=1);

use App\Actions\CreateMentionNotifications;
use App\Actions\UpdateCommit;
use App\Actions\UpdateCommitStatuses;
use App\Enums\CommitStatus;
use App\Enums\NotificationType;
use App\Http\Requests\UpdateCommitRequest;
use App\Jobs\EmbedCommit;
use App\Jobs\ModerateCommit;
use App\Jobs\UnfurlLink;
use App\Models\Commit;
use App\Models\CommitRevision;
use App\Models\LinkPreview;
use App\Models\Notification;
use App\Models\User;
use App\Services\CommitTranslations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config(['app.admins' => ['taylorotwell']]);
    $this->travelTo(now()->startOfSecond());
    Queue::fake([UnfurlLink::class]);
});

it('uses the same trusted-author shortcut as a new commit', function (): void {
    Queue::fake();
    config(['services.openai.key' => 'test-key']);
    $user = User::factory()->create(['created_at' => now()->subMonth()]);
    $commit = Commit::factory()->for($user)->create(['body' => 'Original']);
    $user->followers()->attach(User::factory()->count(10)->create()->modelKeys());
    expect($user->hasGoodReputation())->toBeTrue();

    resolve(UpdateCommit::class)->handle($commit, 'Corrected');

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
    Queue::assertNotPushed(ModerateCommit::class);
});

it('keeps each body it replaces as a revision dated when that body was committed', function (): void {
    $user = User::factory()->create(['username' => 'taylorotwell']);
    $commit = Commit::factory()->for($user)->create(['body' => 'Shiped it']);

    $this->travel(1)->minutes();
    resolve(UpdateCommit::class)->handle($commit, 'Shipped it');
    $firstEdit = now()->toIso8601String();
    $commit->refresh()->forceFill(['status' => CommitStatus::Approved])->save();
    $this->travel(1)->minutes();
    $commit = resolve(UpdateCommit::class)->handle($commit, 'Shipped it :rocket:');

    $commit->refresh();

    expect($commit->body)->toBe('Shipped it 🚀')
        ->and($commit->edited_at?->toIso8601String())->toBe(now()->toIso8601String())
        ->and($commit->revisions->map(fn (CommitRevision $revision): array => [$revision->body, $revision->created_at->toIso8601String()])->all())->toBe([
            ['Shipped it', $firstEdit],
            ['Shiped it', $commit->created_at->toIso8601String()],
        ]);
});

it('leaves the commit alone when the body did not change', function (): void {
    $commit = Commit::factory()->for(User::factory()->create(['username' => 'taylorotwell']))->create(['body' => 'Same 🚀']);

    resolve(UpdateCommit::class)->handle($commit, 'Same :rocket:');

    expect($commit->refresh())->edited_at->toBeNull()
        ->and($commit->revisions()->count())->toBe(0);
});

it('sends the edit of an author who waits for review back to review, unannounced', function (): void {
    config(['services.openai.key' => 'sk-test']);
    Queue::fake();

    $user = User::factory()->create();
    $mentioned = User::factory()->create(['username' => 'mentioned']);
    $parent = Commit::factory()->create();
    $commit = Commit::factory()->for($user)->for($parent, 'parent')->create(['ai_decision' => CommitStatus::Approved]);
    $notification = Notification::factory()->create([
        'recipient_id' => $parent->user_id,
        'actor_id' => $user->id,
        'type' => NotificationType::Replied,
        'commit_id' => $commit->id,
    ]);

    resolve(UpdateCommit::class)->handle($commit, "Thanks @{$mentioned->username}");

    expect($commit->refresh())
        ->status->toBe(CommitStatus::Pending)
        ->ai_decision->toBeNull()
        ->and(Notification::query()->whereKey($notification->id)->exists())->toBeTrue()
        ->and(Notification::query()->where('recipient_id', $mentioned->id)->exists())->toBeFalse();

    Queue::assertPushed(ModerateCommit::class, fn (ModerateCommit $job): bool => $job->commit->is($commit));
    Queue::assertPushed(EmbedCommit::class, fn (EmbedCommit $job): bool => $job->commit->is($commit));
});

it('uses normal administrator approval and updates mention recipients', function (): void {
    $user = User::factory()->create(['username' => 'taylorotwell']);
    $before = User::factory()->create(['username' => 'before']);
    $after = User::factory()->create(['username' => 'after']);
    $commit = Commit::factory()->for($user)->create([
        'body' => "Thanks @{$before->username}",
        'mentions' => ['before' => $before->id],
    ]);
    Notification::factory()->create([
        'recipient_id' => $before->id,
        'actor_id' => $user->id,
        'type' => NotificationType::Mentioned,
        'commit_id' => $commit->id,
        'key' => 'mentioned:'.$commit->id,
    ]);

    resolve(UpdateCommit::class)->handle($commit, "Thanks @{$after->username}");

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved)
        ->and(Notification::query()->where('commit_id', $commit->id)->where('recipient_id', $before->id)->exists())->toBeFalse()
        ->and(Notification::query()->where('commit_id', $commit->id)->where('recipient_id', $after->id)->exists())->toBeTrue();
});

it('keeps the link card while its link stays in the body', function (): void {
    $preview = LinkPreview::factory()->create(['url' => 'https://example.com']);
    $commit = Commit::factory()->for(User::factory()->create(['username' => 'taylorotwell']))->for($preview)->create(['body' => 'Look: https://laravel.com and https://example.com']);

    resolve(UpdateCommit::class)->handle($commit, 'Look at https://example.com');

    expect($commit->refresh()->link_preview_id)->toBe($preview->id);
});

it('moves the link card to the first link when its link is gone', function (): void {
    $commit = Commit::factory()->for(User::factory()->create(['username' => 'taylorotwell']))->for(LinkPreview::factory()->create(['url' => 'https://laravel.cmo']))->create(['body' => 'Look: https://laravel.cmo']);

    resolve(UpdateCommit::class)->handle($commit, 'Look: https://laravel.com');

    expect($commit->refresh()->linkPreview->url)->toBe('https://laravel.com');
    Queue::assertPushed(UnfurlLink::class);
});

it('gives a commit that had no links the card of the first link it gets', function (): void {
    $commit = Commit::factory()->for(User::factory()->create(['username' => 'taylorotwell']))->create(['body' => 'Look']);

    resolve(UpdateCommit::class)->handle($commit, 'Look: https://laravel.com');

    expect($commit->refresh()->linkPreview->url)->toBe('https://laravel.com');
});

it('keeps no card when the author chose none', function (): void {
    $commit = Commit::factory()->for(User::factory()->create(['username' => 'taylorotwell']))->create(['body' => 'Look: https://laravel.com']);

    resolve(UpdateCommit::class)->handle($commit, 'Look: https://laravel.com and https://example.com');

    expect($commit->refresh()->link_preview_id)->toBeNull();
});

it('forgets the translation of the body it replaces', function (): void {
    $commit = Commit::factory()->for(User::factory()->create(['username' => 'taylorotwell']))->create(['body' => 'Hallo']);
    $key = resolve(CommitTranslations::class)->key($commit);
    Cache::put($key, ['source_language' => 'nl']);

    resolve(UpdateCommit::class)->handle($commit, 'Hello');

    expect(Cache::has($key))->toBeFalse();
});

it('checks the current edit window even when given an older model', function (): void {
    $commit = Commit::factory()->create();
    $this->travel(Commit::EDITABLE_MINUTES)->minutes();

    expect(fn () => new UpdateCommitRequest()->ensureEditable($commit))
        ->toThrow(ValidationException::class);

    expect($commit->revisions()->count())->toBe(0);
});

it('checks the edit count under the commit lock', function (): void {
    $commit = Commit::factory()->create();
    CommitRevision::factory()->for($commit)->count(Commit::MAX_EDITS)->create();

    expect(fn () => new UpdateCommitRequest()->ensureEditable($commit))
        ->toThrow(ValidationException::class);

    expect($commit->revisions()->count())->toBe(Commit::MAX_EDITS);
});

it('keeps notification identity and read state after an approved correction', function (): void {
    $mentioned = User::factory()->create(['username' => 'reviewreader']);
    $commit = Commit::factory()->create(['body' => "Thanks @{$mentioned->username}", 'mentions' => [$mentioned->username => $mentioned->id]]);
    resolve(CreateMentionNotifications::class)->handle($commit);
    $notification = Notification::query()->where('commit_id', $commit->id)->sole();
    $notification->update(['read_at' => now()]);

    $readAt = $notification->fresh()->read_at;

    resolve(UpdateCommit::class)->handle($commit, "Thanks @{$mentioned->username}!");
    resolve(UpdateCommitStatuses::class)->handle([$commit->id], CommitStatus::Approved);

    expect(Notification::query()->where('commit_id', $commit->id)->sole()->id)->toBe($notification->id)
        ->and($notification->fresh()->read_at->equalTo($readAt))->toBeTrue();
});

it('starts the checking period at the amendment instead of initial publication', function (): void {
    Queue::fake();
    config(['services.openai.key' => 'test-key']);
    $commit = Commit::factory()->create(['created_at' => now()->subMinutes(14)]);
    resolve(UpdateCommit::class)->handle($commit, 'A corrected commit');
    $this->travel(2)->minutes();

    expect($commit->refresh()->isBeingChecked())->toBeTrue();
    $this->travel(13)->minutes();
    expect($commit->isBeingChecked())->toBeFalse();
});

it('stores the mention map of each historical body', function (): void {
    $mentioned = User::factory()->create();
    $commit = Commit::factory()->create(['body' => "Thanks @{$mentioned->username}", 'mentions' => [$mentioned->username => $mentioned->id]]);
    resolve(UpdateCommit::class)->handle($commit, 'Thanks everyone');

    expect($commit->revisions()->sole()->mentions)->toBe([$mentioned->username => $mentioned->id]);
});
