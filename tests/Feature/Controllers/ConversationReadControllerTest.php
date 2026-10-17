<?php

declare(strict_types=1);

use App\Models\User;

it('lets the sender see when their messages were pulled', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    sendChatMessage($mark, $nuno, 'merged it');
    $latest = sendChatMessage($mark, $nuno, 'feels instant');

    $this->actingAs($mark)->getJson(route('conversation-message.index', $nuno))
        ->assertJsonPath('partner_read_through_id', 0)
        ->assertJsonPath('partner_read_at', null);

    $this->travel(2)->minutes();
    $this->actingAs($nuno)->putJson(route('conversation-read.update', $mark), ['through' => $latest->id])->assertNoContent();

    $this->actingAs($mark)->getJson(route('conversation-message.index', $nuno))
        ->assertJsonPath('partner_read_through_id', $latest->id)
        ->assertJsonPath('partner_read_at', now()->startOfSecond()->toJSON());
});

it('never moves the read position backwards or past the last message', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();
    $first = sendChatMessage($mark, $nuno, 'one');
    $second = sendChatMessage($mark, $nuno, 'two');

    $this->actingAs($nuno)->putJson(route('conversation-read.update', $mark), ['through' => $second->id + 100])->assertNoContent();
    $this->putJson(route('conversation-read.update', $mark), ['through' => $first->id])->assertNoContent();

    $this->actingAs($mark)->getJson(route('conversation-message.index', $nuno))
        ->assertJsonPath('partner_read_through_id', $second->id);
});

it('ignores reads of a conversation that does not exist yet', function (): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();

    $this->actingAs($mark)->putJson(route('conversation-read.update', $nuno), ['through' => 5])->assertNoContent();
});

it('validates the read position', function (mixed $through): void {
    $mark = User::factory()->create();
    $nuno = User::factory()->create();

    $this->actingAs($mark)->putJson(route('conversation-read.update', $nuno), ['through' => $through])
        ->assertUnprocessable()->assertJsonValidationErrors('through');
})->with([null, 'abc', -1]);

it('requires authentication', function (): void {
    $this->putJson(route('conversation-read.update', User::factory()->create()), ['through' => 1])->assertUnauthorized();
});
