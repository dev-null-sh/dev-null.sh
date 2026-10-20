<?php

declare(strict_types=1);

use App\Models\User;

it('pins and unpins a message for the group including outside the latest page', function (): void {
    $owner = User::factory()->create();
    [$member] = followersOf($owner, 1);
    $org = createOrg($owner, [$member]);
    $message = pushToOrg($owner, $org, 'Read this first');

    $this->actingAs($owner)->putJson(route('org-pin.update', $org), ['message_id' => $message->id])->assertNoContent();
    $this->actingAs($member)->getJson(route('org-message.index', [$org, 'after' => $message->id]))
        ->assertOk()->assertJsonPath('pinned_message.id', $message->id);
    $this->actingAs($owner)->putJson(route('org-pin.update', $org), ['message_id' => null])->assertNoContent();
    $this->getJson(route('org-message.index', $org))->assertJsonPath('pinned_message', null);
});

it('only lets maintainers pin messages from their group', function (): void {
    $owner = User::factory()->create();
    [$member] = followersOf($owner, 1);
    $org = createOrg($owner, [$member]);
    $message = pushToOrg($owner, $org);
    $this->actingAs($member)->putJson(route('org-pin.update', $org), ['message_id' => $message->id])->assertForbidden();
    $this->actingAs(User::factory()->create())->putJson(route('org-pin.update', $org), ['message_id' => $message->id])->assertNotFound();

    $other = createOrg($owner, [$member]);
    $this->actingAs($owner)->putJson(route('org-pin.update', $other), ['message_id' => $message->id])->assertUnprocessable();
    $this->putJson(route('org-pin.update', $org), ['message_id' => App\Models\Conversation::findOrFail($org)->messages()->whereNotNull('event')->firstOrFail()->id])->assertUnprocessable();
});

it('hides a pin from members whose history starts after the pinned message', function (): void {
    $owner = User::factory()->create();
    [$member] = followersOf($owner, 1);
    $org = createOrg($owner, [$member]);
    $message = pushToOrg($owner, $org);
    $this->actingAs($owner)->putJson(route('org-pin.update', $org), ['message_id' => $message->id])->assertNoContent();
    App\Models\Conversation::findOrFail($org)->participants()->where('user_id', $member->id)->update(['visible_from_id' => $message->id]);
    $this->actingAs($member)->getJson(route('org-message.index', $org))->assertOk()->assertJsonPath('pinned_message', null);
});
