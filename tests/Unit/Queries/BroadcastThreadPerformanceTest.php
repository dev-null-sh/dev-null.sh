<?php

declare(strict_types=1);

use App\Actions\CreateBroadcast;
use App\Actions\CreateBroadcastSubscription;
use App\Actions\UpdateConversationTyping;
use App\Enums\BroadcastVisibility;
use App\Models\User;
use App\Queries\ConversationsQuery;
use App\Queries\ConversationThreadQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('polls publishers without loading or checking every subscriber', function (int $subscribers): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $broadcast = resolve(CreateBroadcast::class)->handle($owner, 'broadcast', BroadcastVisibility::Public);
    resolve(CreateBroadcastSubscription::class)->handle($viewer, $broadcast);
    User::factory()->count($subscribers)->create()->each(fn (User $user) => resolve(CreateBroadcastSubscription::class)->handle($user, $broadcast));
    config(['cache.default' => 'database']);
    Cache::put(UpdateConversationTyping::key($owner, $broadcast), true, 60);

    $retrieved = [];
    Event::listen('eloquent.retrieved: '.User::class, function (User $user) use (&$retrieved): void {
        $retrieved[] = $user->id;
    });
    DB::enableQueryLog();
    DB::flushQueryLog();
    $thread = resolve(ConversationThreadQuery::class)->broadcast($viewer, $broadcast, after: 0, reactionsAfter: 0);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(7)
        ->and($retrieved)->toBe([$owner->id])
        ->and($thread['subscriber_count'])->toBe($subscribers + 2)
        ->and($thread['publishers']->modelKeys())->toBe([$owner->id])
        ->and($thread['typing'])->toBe([$owner->username]);
})->with([0, 150]);

it('loads a broadcast inbox without hydrating the subscriber roster', function (int $subscribers): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $broadcast = resolve(CreateBroadcast::class)->handle($owner, 'broadcast', BroadcastVisibility::Public);
    resolve(CreateBroadcastSubscription::class)->handle($viewer, $broadcast);
    User::factory()->count($subscribers)->create()->each(fn (User $user) => resolve(CreateBroadcastSubscription::class)->handle($user, $broadcast));
    $retrieved = [];
    Event::listen('eloquent.retrieved: '.User::class, function (User $user) use (&$retrieved): void {
        $retrieved[] = $user->id;
    });

    $inbox = resolve(ConversationsQuery::class)->get($viewer);

    expect($inbox['conversations'])->toHaveCount(1)
        ->and($inbox['conversations']->first()['members'])->toBeNull()
        ->and($retrieved)->toBe([]);
})->with([0, 150]);

it('does not show stale typing from someone who can no longer publish', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $reader = User::factory()->create();
    $broadcast = resolve(CreateBroadcast::class)->handle($owner, 'broadcast', BroadcastVisibility::Public);
    resolve(CreateBroadcastSubscription::class)->handle($viewer, $broadcast);
    resolve(CreateBroadcastSubscription::class)->handle($reader, $broadcast);
    Cache::put(UpdateConversationTyping::key($reader, $broadcast), true, 60);

    expect(resolve(ConversationThreadQuery::class)->broadcast($viewer, $broadcast)['typing'])->toBe([]);
});
