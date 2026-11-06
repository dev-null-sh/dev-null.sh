<?php

declare(strict_types=1);

use App\Enums\PushAccess;
use App\Models\User;
use App\Queries\PushAccessQuery;
use Illuminate\Support\Facades\DB;

it('resolves the access to many partners at once, in two queries, as it does one by one', function (): void {
    $user = User::factory()->create();
    $off = User::factory()->create(['remotes_enabled' => false]);
    $follower = User::factory()->create();
    $follower->following()->attach($user);
    $stranger = User::factory()->create();
    $open = User::factory()->create();
    $open->following()->attach($user);
    sendChatMessage($user, $open);
    config(['remotes.requests' => true]);
    $waiting = User::factory()->create();
    sendChatMessage($user, $waiting);
    $requester = User::factory()->create();
    sendChatMessage($requester, $user);
    config(['remotes.requests' => false]);
    $partners = collect([$off, $follower, $stranger, $open, $waiting, $requester]);
    $query = resolve(PushAccessQuery::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $access = $query->forMany($user, $partners);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($access->all())->toBe([
        $off->id => PushAccess::Closed,
        $follower->id => PushAccess::Open,
        $stranger->id => PushAccess::Restricted,
        $open->id => PushAccess::Open,
        $waiting->id => PushAccess::Waiting,
        $requester->id => PushAccess::Request,
    ])
        ->and($partners->mapWithKeys(fn (User $partner): array => [$partner->id => $query->get($user, $partner)])->all())->toBe($access->all())
        ->and($queries)->toHaveCount(2);
});
