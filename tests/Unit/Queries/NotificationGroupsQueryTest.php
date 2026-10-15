<?php

declare(strict_types=1);

use App\Actions\CreateCommitStar;
use App\Actions\CreateReplyNotifications;
use App\Actions\CreateUserFollow;
use App\Actions\DeleteCommit;
use App\Actions\DeleteUser;
use App\Enums\NotificationType;
use App\Models\Commit;
use App\Models\Notification;
use App\Models\User;
use App\Queries\NotificationGroupsQuery;
use Illuminate\Support\Facades\DB;

function starAt(User $user, Commit $commit, int $minutesAgo): void
{
    test()->travelTo(now()->subMinutes($minutesAgo), fn () => resolve(CreateCommitStar::class)->handle($user, $commit));
}

function replyAt(User $user, Commit $commit, int $minutesAgo): Commit
{
    return test()->travelTo(now()->subMinutes($minutesAgo), function () use ($user, $commit): Commit {
        $reply = Commit::factory()->for($user)->for($commit, 'parent')->create();

        resolve(CreateReplyNotifications::class)->handle($reply);

        return $reply;
    });
}

function followAt(User $follower, User $user, int $minutesAgo): void
{
    test()->travelTo(now()->subMinutes($minutesAgo), fn () => resolve(CreateUserFollow::class)->handle($follower, $user));
}

it('groups stars per commit, newest stargazer first', function (): void {
    $commit = Commit::factory()->create();
    $stargazers = User::factory()->count(5)->create();

    foreach ($stargazers as $index => $stargazer) {
        starAt($stargazer, $commit, 10 - $index);
    }

    $groups = resolve(NotificationGroupsQuery::class)->paginate($commit->user);

    $group = $groups->items()[0];

    expect($groups->items())->toHaveCount(1)
        ->and($group->type)->toBe(NotificationType::Starred)
        ->and($group->commit_id)->toBe($commit->id)
        ->and($group->actor_id)->toBe($stargazers[4]->id)
        ->and($group->actors_count)->toBe(5)
        ->and($group->actors->pluck('id')->all())->toBe([$stargazers[4]->id, $stargazers[3]->id, $stargazers[2]->id]);
});

it('groups replies per replied commit, counting each replier once', function (): void {
    $commit = Commit::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    replyAt($bob, $commit, 3);
    replyAt($carol, $commit, 2);
    $latest = replyAt($bob, $commit, 1);

    $groups = resolve(NotificationGroupsQuery::class)->paginate($commit->user);

    $group = $groups->items()[0];

    expect($groups->items())->toHaveCount(1)
        ->and($group->type)->toBe(NotificationType::Replied)
        ->and($group->commit_id)->toBe($latest->id)
        ->and($group->subject_id)->toBe($commit->id)
        ->and($group->actors_count)->toBe(2)
        ->and($group->actors->pluck('id')->all())->toBe([$bob->id, $carol->id]);
});

it('keeps commits, stars, replies, mentions, and follows apart', function (): void {
    $user = User::factory()->create();
    [$first, $second] = Commit::factory()->for($user)->count(2)->create()->all();
    $other = User::factory()->create();

    starAt($other, $first, 7);
    starAt($other, $second, 6);
    replyAt($other, $first, 5);
    $this->travelTo(now()->subMinutes(4), fn () => Notification::factory()->for($user, 'recipient')->for($other, 'actor')->mentioned()->create());
    $this->travelTo(now()->subMinutes(3), fn () => Notification::factory()->for($user, 'recipient')->for($other, 'actor')->mentioned()->create());
    followAt($other, $user, 2);
    followAt(User::factory()->create(), $user, 1);

    $groups = resolve(NotificationGroupsQuery::class)->paginate($user)->items();

    expect(array_map(fn (Notification $group): string => $group->type->value, $groups))
        ->toBe(['followed', 'followed', 'mentioned', 'mentioned', 'replied', 'starred', 'starred'])
        ->and(array_map(fn (Notification $group): int => $group->actors_count, $groups))
        ->toBe([1, 1, 1, 1, 1, 1, 1])
        ->and($groups[6]->commit_id)->toBe($first->id)
        ->and($groups[0]->actors->sole()->is($groups[0]->actor))->toBeTrue();
});

it('orders groups by their newest notification', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();

    starAt(User::factory()->create(), $commit, 3);
    followAt(User::factory()->create(), $user, 2);
    starAt(User::factory()->create(), $commit, 1);

    $groups = resolve(NotificationGroupsQuery::class)->paginate($user)->items();

    expect(array_map(fn (Notification $group): string => $group->type->value, $groups))
        ->toBe(['starred', 'followed']);
});

it('counts the unread notifications of each group', function (): void {
    $commit = Commit::factory()->create();

    starAt(User::factory()->create(), $commit, 2);
    starAt(User::factory()->create(), $commit, 1);

    Notification::query()->latest()->firstOrFail()->forceFill(['read_at' => now()])->save();

    $group = resolve(NotificationGroupsQuery::class)->paginate($commit->user)->items()[0];

    expect($group->unread_count)->toBeOne();
});

it('shrinks groups when a stargazer is deleted', function (): void {
    $commit = Commit::factory()->create();
    $stargazers = User::factory()->count(3)->create();

    foreach ($stargazers as $index => $stargazer) {
        starAt($stargazer, $commit, 3 - $index);
    }

    resolve(DeleteUser::class)->handle($stargazers[2]);

    $group = resolve(NotificationGroupsQuery::class)->paginate($commit->user)->items()[0];

    expect($group->actor_id)->toBe($stargazers[1]->id)
        ->and($group->actors_count)->toBe(2);
});

it('drops groups when the commit is deleted', function (): void {
    $commit = Commit::factory()->create();

    starAt(User::factory()->create(), $commit, 2);
    replyAt(User::factory()->create(), $commit, 1);

    resolve(DeleteCommit::class)->handle($commit);

    expect(resolve(NotificationGroupsQuery::class)->paginate($commit->user)->items())->toBeEmpty();
});

it('paginates groups', function (): void {
    $user = User::factory()->create();
    $stargazers = User::factory()->count(2)->create();

    Commit::factory()->for($user)->count(NotificationGroupsQuery::PER_PAGE + 1)->create()
        ->each(function (Commit $commit) use ($stargazers): void {
            foreach ($stargazers as $stargazer) {
                resolve(CreateCommitStar::class)->handle($stargazer, $commit);
            }
        });

    $groups = resolve(NotificationGroupsQuery::class)->paginate($user);

    expect($groups->items())->toHaveCount(NotificationGroupsQuery::PER_PAGE)
        ->and($groups->hasMorePages())->toBeTrue();
});

it('loads any number of groups with the same number of queries', function (int $commits): void {
    $user = User::factory()->create();

    Commit::factory()->for($user)->count($commits)->create()
        ->each(fn (Commit $commit) => User::factory()->count(2)->create()
            ->each(fn (User $stargazer) => resolve(CreateCommitStar::class)->handle($stargazer, $commit)));
    resolve(CreateUserFollow::class)->handle(User::factory()->create(), $user);

    DB::flushQueryLog();
    DB::enableQueryLog();

    resolve(NotificationGroupsQuery::class)->paginate($user);

    expect(DB::getQueryLog())->toHaveCount(5);
})->with([1, 5]);

it('skips loading stacked actors when nothing is grouped', function (): void {
    $user = User::factory()->create();
    resolve(CreateUserFollow::class)->handle(User::factory()->create(), $user);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $group = resolve(NotificationGroupsQuery::class)->paginate($user)->items()[0];

    expect(DB::getQueryLog())->toHaveCount(2)
        ->and($group->actors->sole()->is($group->actor))->toBeTrue();
});

it('counts the unread groups', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();

    starAt(User::factory()->create(), $commit, 2);
    starAt(User::factory()->create(), $commit, 1);
    resolve(CreateUserFollow::class)->handle(User::factory()->create(), $user);
    Notification::factory()->for($user, 'recipient')->read()->create();

    expect(resolve(NotificationGroupsQuery::class)->unreadCount($user))->toBe(2);
});
