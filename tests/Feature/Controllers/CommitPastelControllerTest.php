<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;

it('awards repeated pastéis to commits and their author even with the recipient mode off', function (): void {
    $sender = User::factory()->create(['pasteis_mode' => true]);
    $recipient = User::factory()->create(['pasteis_mode' => false]);
    $commit = Commit::factory()->for($recipient)->create();
    $otherCommit = Commit::factory()->for($recipient)->create();

    $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit))
        ->assertOk()->assertJsonPath('pasteis_count', 1)->assertJsonPath('remaining', 9);
    $this->postJson(route('commit-pastel.store', $commit))
        ->assertOk()->assertJsonPath('pasteis_count', 2)->assertJsonPath('remaining', 8);
    $this->postJson(route('commit-pastel.store', $otherCommit))
        ->assertOk()->assertJsonPath('pasteis_count', 1)->assertJsonPath('remaining', 7);

    expect($recipient->refresh()->pasteis_received)->toBe(3)
        ->and($recipient->pasteis_mode)->toBeFalse()
        ->and($sender->refresh()->pasteis_received)->toBe(0);

    $this->get(route('user.show', $recipient))->assertInertia(fn ($page) => $page
        ->where('pasteisReceived', 3)
        ->where('auth.pasteis.remaining', 7));
    $this->actingAs($recipient)->get(route('user.show', $recipient))->assertInertia(fn ($page) => $page
        ->where('pasteisReceived', null)
        ->where('auth.pasteis', null));
});

it('shares ten awards across posts and restores the allowance at midnight UTC, not after 24 hours', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(23, 59, 59));
    $sender = User::factory()->create(['pasteis_mode' => true]);
    $recipient = User::factory()->create();
    $commit = Commit::factory()->for($recipient)->create();
    $otherCommit = Commit::factory()->for($recipient)->create();

    for ($award = 1; $award <= 10; $award++) {
        $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit))
            ->assertOk()->assertJsonPath('pasteis_count', $award)->assertJsonPath('remaining', 10 - $award);
    }

    $this->postJson(route('commit-pastel.store', $otherCommit))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pastel' => 'you ran out of pastéis. wait until tomorrow.']);

    expect($otherCommit->refresh()->pasteis_count)->toBe(0)
        ->and($recipient->refresh()->pasteis_received)->toBe(10);

    $this->travel(1)->seconds();

    $this->postJson(route('commit-pastel.store', $otherCommit))
        ->assertOk()->assertJsonPath('pasteis_count', 1)->assertJsonPath('remaining', 9)
        ->assertJsonPath('day', '2026-10-17');

    expect($recipient->refresh()->pasteis_received)->toBe(11);
});

it('returns 403 when awarding is disabled or the commit belongs to the sender', function (bool $own): void {
    $sender = User::factory()->create(['pasteis_mode' => $own]);
    $recipient = $own ? $sender : User::factory()->create();
    $commit = Commit::factory()->for($recipient)->create();

    $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit))->assertForbidden();

    expect($commit->refresh()->pasteis_count)->toBe(0)
        ->and($recipient->refresh()->pasteis_received)->toBe(0)
        ->and($sender->refresh()->pasteisRemaining())->toBe(10);
})->with([true, false]);

it('returns 404 for unavailable commits without spending an award', function (string $state): void {
    $sender = User::factory()->create(['pasteis_mode' => true]);
    $commit = Commit::factory()->{$state}()->create();

    $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit))->assertNotFound();

    expect($commit->refresh()->pasteis_count)->toBe(0)
        ->and($commit->user->pasteis_received)->toBe(0)
        ->and($sender->refresh()->pasteisRemaining())->toBe(10);
})->with(['pending', 'rejected', 'trashed']);

it('returns 404 across a block in either direction without spending an award', function (bool $senderBlocks): void {
    $sender = User::factory()->create(['pasteis_mode' => true]);
    $commit = Commit::factory()->create();
    $senderBlocks ? $sender->blocking()->attach($commit->user) : $commit->user->blocking()->attach($sender);

    $this->actingAs($sender)->postJson(route('commit-pastel.store', $commit))->assertNotFound();

    expect($commit->refresh()->pasteis_count)->toBe(0)
        ->and($sender->refresh()->pasteisRemaining())->toBe(10);
})->with([true, false]);

it('returns 401 for guests', function (): void {
    $commit = Commit::factory()->create();

    $this->postJson(route('commit-pastel.store', $commit))->assertUnauthorized();

    expect($commit->refresh()->pasteis_count)->toBe(0);
});
