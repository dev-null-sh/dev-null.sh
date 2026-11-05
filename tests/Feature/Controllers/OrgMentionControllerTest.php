<?php

declare(strict_types=1);

use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('hands members the messages that mention them, latest first, until they clear them', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno, $taylor]);
    $review = pushToOrg($nuno, $org, "@{$mark->handle} can you review the deploy script?");
    pushToOrg($taylor, $org, 'shipping it now');
    $schema = pushToOrg($taylor, $org, "thoughts on the schema @{$mark->handle}?");

    $this->actingAs($mark)->getJson(route('org-message.index', $org))
        ->assertOk()
        ->assertJsonPath('mentions.*.id', [$schema->id, $review->id])
        ->assertJsonPath('mentions_count', 2)
        ->assertJsonPath('mentions.1.body', "@{$mark->handle} can you review the deploy script?")
        ->assertJsonPath('mentions.1.user.handle', $nuno->handle);
    $this->getJson(route('org-message.index', [$org, 'after' => $schema->id]))->assertJsonPath('mentions.*.id', [$schema->id, $review->id]);

    $this->deleteJson(route('org-mention.destroy', [$org, 'message_id' => $review->id]))
        ->assertOk()
        ->assertJsonPath('mentions.*.id', [$schema->id])
        ->assertJsonPath('mentions_count', 1);

    $this->getJson(route('org-message.index', $org))->assertJsonPath('mentions.*.id', [$schema->id]);
    $this->actingAs($taylor)->getJson(route('org-message.index', $org))->assertJsonPath('mentions', []);
});

it('clears every mention in one org at once, leaving the other orgs', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $core = createOrg($mark, [$nuno]);
    $infra = createOrg($mark, [$nuno], 'infra');
    pushToOrg($nuno, $core, "@{$mark->handle} one");
    pushToOrg($nuno, $core, "@{$mark->handle} two");
    $cache = pushToOrg($nuno, $infra, "@{$mark->handle} the cache is up");

    $this->actingAs($mark)->deleteJson(route('org-mention.destroy', $core))->assertOk()->assertJsonPath('mentions', [])->assertJsonPath('mentions_count', 0);

    $this->getJson(route('org-message.index', $core))->assertJsonPath('mentions', []);
    $this->getJson(route('org-message.index', $infra))->assertJsonPath('mentions.*.id', [$cache->id]);
});

it('counts every mention, while only the latest 99 come along', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $messages = Message::factory()->count(100)->create(['conversation_id' => $org, 'user_id' => $nuno->id]);
    $mark->mentions()->attach($messages->modelKeys());

    $this->actingAs($mark)->getJson(route('org-message.index', $org))
        ->assertJsonCount(99, 'mentions')
        ->assertJsonPath('mentions.0.id', $messages->last()->id)
        ->assertJsonPath('mentions_count', 100);
});

it('does not mention the sender, people across a block, or people muting the sender', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor, $jess] = followersOf($mark, 3);
    $org = createOrg($mark, [$nuno, $taylor, $jess]);
    $taylor->muting()->attach($nuno);
    $jess->blocking()->attach($nuno);

    pushToOrg($nuno, $org, "@{$nuno->handle} @{$taylor->handle} @{$jess->handle} standup?");

    foreach ([$nuno, $taylor, $jess] as $member) {
        $this->actingAs($member)->getJson(route('org-message.index', $org))->assertJsonPath('mentions', []);
    }
});

it('forgets the mentions of a member who leaves', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    pushToOrg($mark, $org, "@{$nuno->handle} welcome");

    $this->actingAs($nuno)->deleteJson(route('org-member.destroy', [$org, $nuno->handle]))->assertNoContent();
    $this->actingAs($mark)->postJson(route('org-member.store', $org), ['members' => [$nuno->handle], 'share_history' => true])->assertNoContent();

    $this->actingAs($nuno)->getJson(route('org-message.index', $org))->assertJsonPath('mentions', []);
});

it("tells the user's other devices only when a mention was cleared", function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $message = pushToOrg($nuno, $org, "@{$mark->handle} review?");
    Event::fake([UsersUpdated::class]);

    $this->actingAs($mark)->deleteJson(route('org-mention.destroy', $org))->assertOk();
    $this->deleteJson(route('org-mention.destroy', [$org, 'message_id' => $message->id]))->assertOk();

    Event::assertDispatchedTimes(UsersUpdated::class, 1);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Conversation && $event->userIds === [$mark->id]);
});

it('keeps non-members out', function (): void {
    $mark = User::factory()->create();
    [$nuno, $stranger] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs($stranger)->deleteJson(route('org-mention.destroy', $org))->assertNotFound();
});
