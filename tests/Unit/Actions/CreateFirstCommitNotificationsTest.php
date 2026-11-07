<?php

declare(strict_types=1);

use App\Actions\CreateFirstCommitNotifications;
use App\Actions\CreateTomFriendship;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it("notifies the author's followers about their first commit", function (): void {
    $author = User::factory()->create();
    $followers = User::factory()->count(2)->create();
    $author->followers()->attach($followers);
    $commit = Commit::factory()->for($author)->create();

    resolve(CreateFirstCommitNotifications::class)->handle($commit);

    foreach ($followers as $follower) {
        $notification = $follower->notifications()->sole();

        expect($notification->type)->toBe(NotificationType::FirstCommit)
            ->and($notification->actor_id)->toBe($author->id)
            ->and($notification->commit_id)->toBe($commit->id);
    }
});

it('only notifies about the first commit', function (): void {
    $author = User::factory()->create();
    $author->followers()->attach(User::factory()->create());
    Commit::factory()->for($author)->create(['created_at' => now()->subDay()]);
    $second = Commit::factory()->for($author)->create();

    resolve(CreateFirstCommitNotifications::class)->handle($second);

    expect(Notification::query()->count())->toBe(0);
});

it('counts a first commit that was deleted since', function (): void {
    $author = User::factory()->create();
    $author->followers()->attach(User::factory()->create());
    Commit::factory()->for($author)->create(['created_at' => now()->subDay(), 'deleted_at' => now()]);
    $second = Commit::factory()->for($author)->create();

    resolve(CreateFirstCommitNotifications::class)->handle($second);

    expect(Notification::query()->count())->toBe(0);
});

it('waits for the first commit to be approved', function (): void {
    $author = User::factory()->create();
    $author->followers()->attach(User::factory()->create());

    resolve(CreateFirstCommitNotifications::class)->handle(
        Commit::factory()->for($author)->pending()->create(),
        Commit::factory()->for($author)->rejected()->create(),
    );

    expect(Notification::query()->count())->toBe(0);
});

it('notifies about the first commit approved after a rejected one', function (): void {
    $author = User::factory()->create();
    $follower = User::factory()->create();
    $author->followers()->attach($follower);
    Commit::factory()->for($author)->rejected()->create(['created_at' => now()->subDay()]);
    $commit = Commit::factory()->for($author)->create();

    resolve(CreateFirstCommitNotifications::class)->handle($commit);

    expect($follower->notifications()->sole()->commit_id)->toBe($commit->id);
});

it('does not count replies as a first commit', function (): void {
    $author = User::factory()->create();
    $author->followers()->attach(User::factory()->create());
    $reply = Commit::factory()->for($author)->for(Commit::factory(), 'parent')->create();

    resolve(CreateFirstCommitNotifications::class)->handle($reply);

    expect(Notification::query()->count())->toBe(0);
});

it('looks nothing up for replies', function (): void {
    $reply = Commit::factory()->for(Commit::factory(), 'parent')->create();

    DB::enableQueryLog();
    resolve(CreateFirstCommitNotifications::class)->handle($reply);

    expect(DB::getQueryLog())->toBeEmpty();
});

it('notifies only once, about the earliest of first commits approved together', function (): void {
    $author = User::factory()->create();
    $follower = User::factory()->create();
    $author->followers()->attach($follower);
    $first = Commit::factory()->for($author)->create(['created_at' => now()->subMinute()]);
    $second = Commit::factory()->for($author)->create();

    $action = resolve(CreateFirstCommitNotifications::class);
    $action->handle($second, $first);
    $action->handle($first);

    expect($follower->notifications()->sole()->commit_id)->toBe($first->id);
});

it('leaves out bots, and followers who mute the author or are across a block', function (): void {
    $author = User::factory()->create();
    resolve(CreateTomFriendship::class)->handle($author);
    [$follower, $muter, $blocker, $blocked] = User::factory()->count(4)->create()->all();
    $author->followers()->attach([$follower->id, $muter->id, $blocker->id, $blocked->id]);
    $muter->muting()->attach($author);
    $blocker->blocking()->attach($author);
    $author->blocking()->attach($blocked);

    resolve(CreateFirstCommitNotifications::class)->handle(Commit::factory()->for($author)->create());

    expect(Notification::query()->pluck('recipient_id')->all())->toBe([$follower->id]);
});

it('does not notify about the first commit of a bot', function (): void {
    $author = User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]);
    $author->followers()->attach(User::factory()->create());

    resolve(CreateFirstCommitNotifications::class)->handle(Commit::factory()->for($author)->create());

    expect(Notification::query()->count())->toBe(0);
});

it('leaves out followers it mentions or quotes, who already hear about it', function (): void {
    $author = User::factory()->create();
    [$follower, $mentioned, $quoted] = User::factory()->count(3)->create()->all();
    $author->followers()->attach([$follower->id, $mentioned->id, $quoted->id]);
    $commit = Commit::factory()->for($author)->create([
        'mentions' => [$mentioned->handle => $mentioned->id],
        'quoted_commit_id' => Commit::factory()->for($quoted)->create()->id,
    ]);

    resolve(CreateFirstCommitNotifications::class)->handle($commit);

    expect(Notification::query()->pluck('recipient_id')->all())->toBe([$follower->id]);
});
