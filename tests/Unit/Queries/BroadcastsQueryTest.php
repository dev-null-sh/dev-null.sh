<?php

declare(strict_types=1);

use App\Actions\CreateBroadcast;
use App\Actions\CreateBroadcastSubscription;
use App\Enums\BroadcastVisibility;
use App\Models\BroadcastInvitation;
use App\Models\User;
use App\Queries\BroadcastsQuery;
use Illuminate\Support\Facades\DB;

it('loads and serializes a directory page with a fixed query budget', function (int $count): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    foreach (range(1, $count) as $index) {
        resolve(CreateBroadcast::class)->handle($owner, "channel {$index}", BroadcastVisibility::Public);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    $page = resolve(BroadcastsQuery::class)->directory($viewer);
    $page['broadcasts']->toJson();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($page['broadcasts'])->toHaveCount($count)
        ->and($queries)->toBe(3)
        ->and($page['broadcasts']->pluck('membership')->unique()->all())->toBe(['none'])
        ->and($page['broadcasts']->pluck('subscriber_count')->unique()->all())->toBe([1]);
})->with([1, BroadcastsQuery::PAGE]);

it('uses the same metadata for owners, subscribers and invitees without leaking viewer state', function (): void {
    $owner = User::factory()->create();
    $subscriber = User::factory()->create();
    $invitee = User::factory()->create();
    $public = resolve(CreateBroadcast::class)->handle($owner, 'open channel', BroadcastVisibility::Public);
    $private = resolve(CreateBroadcast::class)->handle($owner, 'private channel', BroadcastVisibility::Invite);
    resolve(CreateBroadcastSubscription::class)->handle($subscriber, $public);
    BroadcastInvitation::factory()->for($private)->for($invitee)->create();
    $query = resolve(BroadcastsQuery::class);

    expect($query->summary($owner, $public)['membership'])->toBe('owner')
        ->and($query->summary($subscriber, $public)['membership'])->toBe('subscriber')
        ->and($query->summary($invitee, $public)['membership'])->toBe('none')
        ->and($query->summary($invitee, $private)['membership'])->toBe('invited');

    $listed = $query->directory($invitee)['broadcasts']->keyBy('id');
    expect($listed[$public->id]['subscriber_count'])->toBe(2)
        ->and($listed[$private->id]['subscriber_count'])->toBe(1)
        ->and($listed[$private->id]['membership'])->toBe('invited');
});

it('bounds the latest public profile listings with a single query', function (): void {
    $owner = User::factory()->create();
    $public = collect(range(1, BroadcastsQuery::PAGE + 1))->map(fn (int $index) => resolve(CreateBroadcast::class)->handle($owner, "public {$index}", BroadcastVisibility::Public));
    $private = resolve(CreateBroadcast::class)->handle($owner, 'secret', BroadcastVisibility::Invite);
    $other = resolve(CreateBroadcast::class)->handle(User::factory()->create(), 'other owner', BroadcastVisibility::Public);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $listed = resolve(BroadcastsQuery::class)->of($owner);
    $listed->toJson();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($listed)->toHaveCount(BroadcastsQuery::PAGE)
        ->and($listed->first()['id'])->toBe($public->last()->id)
        ->and($listed->pluck('id')->all())->not->toContain($public->first()->id, $private->id, $other->id)
        ->and($queries)->toBe(1);
});

it('ignores a pagination cursor belonging to a hidden broadcast', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $older = resolve(CreateBroadcast::class)->handle($owner, 'older', BroadcastVisibility::Public);
    $hidden = resolve(CreateBroadcast::class)->handle($owner, 'hidden', BroadcastVisibility::Invite);
    $newer = resolve(CreateBroadcast::class)->handle($owner, 'newer', BroadcastVisibility::Public);

    $page = resolve(BroadcastsQuery::class)->directory($viewer, before: $hidden->id);

    expect($page['broadcasts']->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

it('keeps filtered cursor pages within a fixed query budget', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    foreach (range(1, BroadcastsQuery::PAGE + 1) as $index) {
        resolve(CreateBroadcast::class)->handle($owner, "channel {$index}", BroadcastVisibility::Public);
    }

    $query = resolve(BroadcastsQuery::class);
    $first = $query->directory($viewer, $owner->username);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $next = $query->directory($viewer, $owner->username, $first['broadcasts']->last()['id']);
    $next['broadcasts']->toJson();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($first['has_more'])->toBeTrue()
        ->and($next['broadcasts'])->toHaveCount(1)
        ->and($next['has_more'])->toBeFalse()
        ->and($queries)->toBe(4);
});
