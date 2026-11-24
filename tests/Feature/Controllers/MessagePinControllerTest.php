<?php

declare(strict_types=1);

use App\Actions\UpdateMessagePin;
use App\Enums\Feature;
use App\Models\Conversation;
use App\Models\Guild;
use App\Models\Message;
use App\Models\User;
use App\Services\Features;

it('lets maintainers pin a message for the whole org, once', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $message = pushToOrg($nuno, $org, 'deploys go out on fridays now');
    $version = Conversation::query()->findOrFail($org)->reactions_version;

    $this->actingAs($mark)->putJson(route('message-pin.update', $message))
        ->assertOk()
        ->assertJsonCount(1, 'pinned')
        ->assertJsonPath('pinned.0.id', $message->id)
        ->assertJsonPath('pinned.0.body', 'deploys go out on fridays now')
        ->assertJsonPath('pinned.0.user.handle', $nuno->handle)
        ->assertJsonPath('reactions_version', $version + 1);

    $pinnedAt = $message->fresh()?->pinned_at;

    expect($pinnedAt)->not->toBeNull()
        ->and(Conversation::query()->findOrFail($org)->reactions_version)->toBe($version + 1);

    $this->travel(5)->minutes();

    $this->putJson(route('message-pin.update', $message))
        ->assertOk()
        ->assertJsonPath('pinned.*.id', [$message->id])
        ->assertJsonPath('reactions_version', $version + 1);

    expect($message->fresh()?->pinned_at?->equalTo($pinnedAt))->toBeTrue();
});

it('lets maintainers unpin a message, however often', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $kept = pushToOrg($nuno, $org, 'kept');
    $dropped = pushToOrg($nuno, $org, 'dropped');
    $this->actingAs($mark)->putJson(route('message-pin.update', $kept))->assertOk();
    $this->putJson(route('message-pin.update', $dropped))->assertOk();
    $version = Conversation::query()->findOrFail($org)->reactions_version;

    $this->deleteJson(route('message-pin.destroy', $dropped))
        ->assertOk()
        ->assertJsonPath('pinned.*.id', [$kept->id])
        ->assertJsonPath('reactions_version', $version + 1);

    expect($dropped->fresh()?->pinned_at)->toBeNull()
        ->and(Conversation::query()->findOrFail($org)->reactions_version)->toBe($version + 1);

    $this->deleteJson(route('message-pin.destroy', $dropped))
        ->assertOk()
        ->assertJsonPath('pinned.*.id', [$kept->id])
        ->assertJsonPath('reactions_version', $version + 1);
});

it('keeps guild badges on surviving pins without exposing the unpinned reply target', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    resolve(Features::class)->update(Feature::Guilds, true, false);
    Guild::factory()->create(['handle' => 'rust', 'name' => 'Rust']);
    Guild::factory()->create(['handle' => 'hidden']);
    $hidden = pushToOrg($nuno, $org, '#hidden');
    $kept = Message::factory()->create(['conversation_id' => $org, 'user_id' => $nuno->id, 'body' => '#rust', 'reply_to_id' => $hidden->id]);
    $other = pushToOrg($nuno, $org, 'another pin');
    $this->actingAs($mark)->putJson(route('message-pin.update', $kept))->assertOk()
        ->assertJsonPath('pinned.0.guild_mentions.rust.name', 'Rust')
        ->assertJsonCount(1, 'pinned.0.guild_mentions')
        ->assertJsonMissingPath('pinned.0.reply_to_id')
        ->assertJsonMissingPath('pinned.0.reply_to');

    $pinned = $this->putJson(route('message-pin.update', $other))
        ->assertOk()->assertJsonCount(2, 'pinned')->json('pinned');
    $shown = collect($pinned)->firstWhere('id', $kept->id);

    expect($shown['guild_mentions'])->toHaveCount(1)->toHaveKey('rust')
        ->and($shown)->not->toHaveKeys(['reply_to_id', 'reply_to']);

    $this->deleteJson(route('message-pin.destroy', $other))
        ->assertOk()
        ->assertJsonPath('pinned.0.id', $kept->id)
        ->assertJsonPath('pinned.0.guild_mentions.rust.name', 'Rust')
        ->assertJsonCount(1, 'pinned.0.guild_mentions')
        ->assertJsonMissingPath('pinned.0.reply_to_id')
        ->assertJsonMissingPath('pinned.0.reply_to');
});

it('only lets maintainers pin and unpin', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $message = pushToOrg($nuno, $org);

    $this->actingAs($nuno)->putJson(route('message-pin.update', $message))
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can pin messages.');
    $this->actingAs($mark)->putJson(route('message-pin.update', $message))->assertOk();
    $this->actingAs($nuno)->deleteJson(route('message-pin.destroy', $message))
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can unpin messages.');

    expect($message->fresh()?->pinned_at)->not->toBeNull();
});

it('is not there for people outside the org', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $message = pushToOrg($nuno, $org);

    $this->actingAs(User::factory()->create())->putJson(route('message-pin.update', $message))->assertNotFound();
    $this->deleteJson(route('message-pin.destroy', $message))->assertNotFound();
});

it('is not there while the maintainer has Remotes off', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $message = pushToOrg($nuno, $org);
    $mark->update(['remotes_enabled' => false]);

    $this->actingAs($mark)->putJson(route('message-pin.update', $message))->assertNotFound();
    $this->deleteJson(route('message-pin.destroy', $message))->assertNotFound();
});

it('pins neither direct messages nor org events', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $direct = sendChatMessage($mark, $nuno);
    $org = createOrg($mark, [$nuno]);
    $event = Message::query()->where('conversation_id', $org)->whereNotNull('event')->sole();

    $this->actingAs($mark)->putJson(route('message-pin.update', $direct))->assertNotFound();
    $this->deleteJson(route('message-pin.destroy', $direct))->assertNotFound();
    $this->putJson(route('message-pin.update', $event))->assertNotFound();
});

it('pins only within the history the maintainer can see, but unpins whatever the org pinned', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno]);
    $before = pushToOrg($nuno, $org, 'from before taylor joined');
    $this->actingAs($mark)->postJson(route('org-member.store', $org), ['members' => [$taylor->handle], 'share_history' => false])->assertNoContent();
    $this->putJson(route('org-member.update', [$org, $taylor]), ['role' => 'maintainer'])->assertNoContent();

    $this->actingAs($taylor)->putJson(route('message-pin.update', $before))->assertNotFound();
    $this->actingAs($mark)->putJson(route('message-pin.update', $before))->assertOk();
    $this->actingAs($taylor)->deleteJson(route('message-pin.destroy', $before))->assertOk()->assertJsonPath('pinned', []);
});

it('caps how many messages an org pins', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $pinned = Message::factory()->count(Conversation::MAX_PINNED_MESSAGES)->pinned()->create(['conversation_id' => $org, 'user_id' => $nuno->id]);
    $message = pushToOrg($nuno, $org);

    $this->actingAs($mark)->putJson(route('message-pin.update', $message))
        ->assertForbidden()
        ->assertJsonPath('message', 'An org can pin up to 25 messages. Unpin one first.');
    $this->putJson(route('message-pin.update', $pinned->first()))->assertOk()->assertJsonCount(Conversation::MAX_PINNED_MESSAGES, 'pinned');
    $this->deleteJson(route('message-pin.destroy', $pinned->first()))->assertOk();
    $this->putJson(route('message-pin.update', $message))->assertOk()->assertJsonPath('pinned.0.id', $message->id);
});

it('leaves a message unpinned when another pin fills the org after the request was allowed', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    Message::factory()->count(Conversation::MAX_PINNED_MESSAGES - 1)->pinned()->create(['conversation_id' => $org, 'user_id' => $nuno->id]);
    $theirs = pushToOrg($nuno, $org, 'pinned by another maintainer meanwhile');
    $mine = pushToOrg($nuno, $org, 'mine');
    $version = Conversation::query()->findOrFail($org)->reactions_version;

    app()->resolving(UpdateMessagePin::class, fn () => Message::query()->whereKey($theirs->id)->update(['pinned_at' => now()]));

    $response = $this->actingAs($mark)->putJson(route('message-pin.update', $mine))
        ->assertOk()
        ->assertJsonCount(Conversation::MAX_PINNED_MESSAGES, 'pinned')
        ->assertJsonPath('reactions_version', $version);

    expect($response->json('pinned.*.id'))->toContain($theirs->id)->not->toContain($mine->id)
        ->and($mine->fresh()?->pinned_at)->toBeNull();
});
