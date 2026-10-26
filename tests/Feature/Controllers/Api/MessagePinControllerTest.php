<?php

declare(strict_types=1);

use App\Actions\CreateUserBan;
use App\Enums\ModerationReason;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create(['handle' => 'octocat']);
    $this->keyPair = apiKeyPairFor($this->user);
    $this->nuno = User::factory()->withRemotes()->create(['handle' => 'nunomaduro']);
});

it('pins a message for the whole org once, and answers with the pinned messages as the thread shows them', function (): void {
    [$taylor] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$taylor]);
    $message = pushToOrg($taylor, $org, 'deploys go out on fridays now');
    $version = Conversation::query()->findOrFail($org)->reactions_version;

    $pinned = signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $message))
        ->assertOk()
        ->assertJsonCount(1, 'pinned')
        ->assertJsonPath('pinned.0.id', $message->id)
        ->assertJsonPath('pinned.0.body', 'deploys go out on fridays now')
        ->assertJsonPath('pinned.0.user.lane', $taylor->lane())
        ->assertJsonPath('pinned.0.created_at', $message->created_at->toIso8601ZuluString())
        ->assertJsonPath('reactions_version', $version + 1)
        ->json('pinned');

    signedApiRequest($this->keyPair, 'GET', route('api-org-message.index', $org))->assertJsonPath('data.pinned', $pinned);

    $pinnedAt = $message->fresh()?->pinned_at;

    expect($pinned[0]['pinned_at'])->toBe($pinnedAt?->toIso8601ZuluString());

    $this->travel(5)->minutes();

    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $message))
        ->assertOk()
        ->assertJsonPath('pinned.*.id', [$message->id])
        ->assertJsonPath('reactions_version', $version + 1);

    expect($pinnedAt)->not->toBeNull()
        ->and($message->fresh()?->pinned_at?->equalTo($pinnedAt))->toBeTrue();
});

it('unpins a message, however often', function (): void {
    [$taylor] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$taylor]);
    $kept = pushToOrg($taylor, $org, 'kept');
    $dropped = pushToOrg($taylor, $org, 'dropped');
    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $kept))->assertOk();
    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $dropped))->assertOk();

    $version = Conversation::query()->findOrFail($org)->reactions_version;

    signedApiRequest($this->keyPair, 'DELETE', route('api-message-pin.destroy', $dropped))
        ->assertOk()
        ->assertJsonPath('pinned.*.id', [$kept->id])
        ->assertJsonPath('reactions_version', $version + 1);

    expect($dropped->fresh()?->pinned_at)->toBeNull();

    signedApiRequest($this->keyPair, 'DELETE', route('api-message-pin.destroy', $dropped))
        ->assertOk()
        ->assertJsonPath('pinned.*.id', [$kept->id])
        ->assertJsonPath('reactions_version', $version + 1);
});

it('only lets maintainers pin and unpin', function (): void {
    $this->user->following()->attach($this->nuno);
    $org = createOrg($this->nuno, [$this->user]);
    $message = pushToOrg($this->nuno, $org);

    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $message))
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can pin messages.');

    $this->actingAs($this->nuno)->putJson(route('message-pin.update', $message))->assertOk();

    signedApiRequest($this->keyPair, 'DELETE', route('api-message-pin.destroy', $message))
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can unpin messages.');

    expect($message->fresh()?->pinned_at)->not->toBeNull();
});

it('is not there outside the orgs the user is in, or while they have Remotes off', function (string $case, string $method): void {
    [$taylor] = followersOf($this->user, 1);
    $message = match ($case) {
        'not a member' => pushToOrg($this->nuno, createOrg($this->nuno, followersOf($this->nuno, 1))),
        'direct message' => sendChatMessage($this->nuno, $this->user),
        default => pushToOrg($taylor, createOrg($this->user, [$taylor])),
    };

    if ($case === 'remotes off') {
        $this->user->update(['remotes_enabled' => false]);
    }

    signedApiRequest($this->keyPair, $method, route($method === 'PUT' ? 'api-message-pin.update' : 'api-message-pin.destroy', $message))->assertNotFound();

    expect($message->fresh()?->pinned_at)->toBeNull();
})->with(['not a member', 'direct message', 'remotes off'])->with(['PUT', 'DELETE']);

it('pins no org events', function (): void {
    [$taylor] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$taylor]);
    $event = Message::query()->where('conversation_id', $org)->whereNotNull('event')->sole();

    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $event))->assertNotFound();
});

it('pins only within the history the maintainer can see, but unpins whatever the org pinned', function (): void {
    [$taylor] = followersOf($this->nuno, 1);
    $this->user->following()->attach($this->nuno);
    $org = createOrg($this->nuno, [$taylor]);
    $before = pushToOrg($taylor, $org, 'from before octocat joined');
    $this->actingAs($this->nuno)->postJson(route('org-member.store', $org), ['members' => [$this->user->handle], 'share_history' => false])->assertNoContent();
    $this->putJson(route('org-member.update', [$org, $this->user]), ['role' => 'maintainer'])->assertNoContent();

    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $before))->assertNotFound();

    $this->actingAs($this->nuno)->putJson(route('message-pin.update', $before))->assertOk();

    signedApiRequest($this->keyPair, 'DELETE', route('api-message-pin.destroy', $before))->assertOk()->assertJsonPath('pinned', []);
});

it('leaves out what a pinned message replies to, which may be from before the member history', function (): void {
    [$nuno, $taylor] = followersOf($this->user, 2);
    $org = createOrg($this->user, [$nuno]);
    $question = pushToOrg($nuno, $org, 'where does the deploy key live?');
    $answer = $this->actingAs($this->user)->postJson(route('org-message.store', $org), ['body' => 'in the vault', 'client_id' => Str::uuid()->toString(), 'reply_to_id' => $question->id])
        ->assertCreated()
        ->json('message.id');
    $this->postJson(route('org-member.store', $org), ['members' => [$taylor->handle], 'share_history' => false])->assertNoContent();
    $this->putJson(route('org-member.update', [$org, $taylor]), ['role' => 'maintainer'])->assertNoContent();
    $welcome = pushToOrg($nuno, $org, 'welcome taylor');
    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $answer))->assertOk();
    $taylorKeys = apiKeyPairFor($taylor);

    $lists = [
        signedApiRequest($taylorKeys, 'PUT', route('api-message-pin.update', $welcome))->assertOk()->json('pinned'),
        signedApiRequest($taylorKeys, 'DELETE', route('api-message-pin.destroy', $welcome))->assertOk()->json('pinned'),
        signedApiRequest($taylorKeys, 'GET', route('api-org-message.index', $org))->assertOk()->json('data.pinned'),
    ];

    foreach ($lists as $pinned) {
        expect(array_column($pinned, 'id'))->toContain($answer);

        foreach ($pinned as $message) {
            expect($message)->not->toHaveKeys(['reply_to', 'reply_to_id'])
                ->and(Arr::dot(Arr::except($message, 'user')))->not->toContain($question->id);
        }
    }
});

it('caps how many messages an org pins', function (): void {
    [$taylor] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$taylor]);
    $pinned = Message::factory()->count(Conversation::MAX_PINNED_MESSAGES)->pinned()->create(['conversation_id' => $org, 'user_id' => $taylor->id]);
    $message = pushToOrg($taylor, $org);

    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $message))
        ->assertForbidden()
        ->assertJsonPath('message', 'An org can pin up to 25 messages. Unpin one first.');

    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $pinned->first()))
        ->assertOk()
        ->assertJsonCount(Conversation::MAX_PINNED_MESSAGES, 'pinned');
});

it('refuses pins from timed out and banned users', function (string $restriction): void {
    [$taylor] = followersOf($this->user, 1);
    $message = pushToOrg($taylor, createOrg($this->user, [$taylor]));

    if ($restriction === 'timeout') {
        UserTimeout::factory()->for($this->user)->create();
    } else {
        resolve(CreateUserBan::class)->handle($this->user, ModerationReason::Spam);
    }

    signedApiRequest($this->keyPair, 'PUT', route('api-message-pin.update', $message))
        ->assertForbidden()
        ->assertJsonPath('code', $restriction === 'timeout' ? 'timed_out' : 'banned');

    expect($message->fresh()?->pinned_at)->toBeNull();
})->with(['timeout', 'banned']);

it('rejects requests that are not signed', function (): void {
    [$taylor] = followersOf($this->user, 1);
    $message = pushToOrg($taylor, createOrg($this->user, [$taylor]));

    $this->putJson(route('api-message-pin.update', $message))->assertUnauthorized();
});
