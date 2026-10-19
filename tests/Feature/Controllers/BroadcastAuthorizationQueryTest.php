<?php

declare(strict_types=1);

use App\Actions\CreateBroadcast;
use App\Actions\CreateBroadcastPublisher;
use App\Actions\CreateBroadcastSubscription;
use App\Enums\BroadcastVisibility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('resolves the callers broadcast role once per operation', function (string $operation): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $broadcast = resolve(CreateBroadcast::class)->handle($owner, 'broadcast', BroadcastVisibility::Public);
    if ($operation !== 'invite') {
        resolve(CreateBroadcastSubscription::class)->handle($member, $broadcast);
    }

    if ($operation === 'demote') {
        resolve(CreateBroadcastPublisher::class)->handle($broadcast, $member);
    }

    $target = ['broadcast' => $broadcast, 'member' => $member->username];
    [$method, $name, $parameters, $data] = match ($operation) {
        'invite' => ['POST', 'broadcast-invitation.store', $broadcast, ['member' => $member->username]],
        'promote' => ['POST', 'broadcast-publisher.store', ['broadcast' => $broadcast, 'publisher' => $member->username], []],
        'demote' => ['DELETE', 'broadcast-publisher.destroy', ['broadcast' => $broadcast, 'publisher' => $member->username], []],
        'rename' => ['PUT', 'broadcast.update', $broadcast, ['name' => 'renamed']],
        'members' => ['GET', 'broadcast-member.index', $broadcast, []],
        'kick' => ['DELETE', 'broadcast-member.destroy', $target, []],
        'ban' => ['PUT', 'broadcast-member.update', $target, ['banned' => true]],
        'typing' => ['PUT', 'broadcast-typing.update', $broadcast, []],
        'push' => ['POST', 'broadcast-message.store', $broadcast, ['body' => 'a broadcast update', 'client_id' => Str::uuid()->toString()]],
        'leave' => ['DELETE', 'broadcast-subscription.destroy', $broadcast, []],
    };
    $actor = $operation === 'leave' ? $member : $owner;

    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->actingAs($actor)->json($method, route($name, $parameters), $data)->assertSuccessful();
    $lookups = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with($query['query'], 'select * from "conversation_participants"')
        && str_ends_with($query['query'], 'limit 1')
        && in_array($actor->id, $query['bindings'], true));
    DB::disableQueryLog();

    expect($lookups)->toHaveCount(1);
})->with(['invite', 'promote', 'demote', 'rename', 'members', 'kick', 'ban', 'typing', 'push', 'leave']);
