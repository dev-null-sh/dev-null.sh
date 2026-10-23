<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Carbon\CarbonInterface;

beforeEach(function (): void {
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

it('mutes a conversation for you only, until the chosen duration ends', function (string $duration, Closure $until): void {
    $this->freezeSecond();
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $expected = $until()->toJSON();

    $this->actingAs($mark)->putJson(route('conversation-mute.update', $nuno), ['duration' => $duration])
        ->assertOk()
        ->assertExactJson(['muted_until_at' => $expected]);

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.muted_until_at', $expected);
    $this->getJson(route('conversation-message.index', $nuno))->assertJsonPath('muted_until_at', $expected);

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonPath('conversations.0.muted_until_at', null);
    $this->getJson(route('conversation-message.index', $mark))->assertJsonPath('muted_until_at', null);
})->with([
    'an hour' => ['hour', fn (): CarbonInterface => now()->addHour()],
    'a week' => ['week', fn (): CarbonInterface => now()->addWeek()],
    'forever' => ['forever', fn (): CarbonInterface => now()->parse('9999-12-31T00:00:00Z')],
]);

it('mutes until the next midnight in your account timezone and persists the same UTC expiration', function (?string $timezone, string $now, string $expected): void {
    $this->travelTo(now()->parse($now));
    $mark = User::factory()->withRemotes()->create(['timezone' => $timezone]);
    $nuno = User::factory()->withRemotes()->create(['timezone' => 'Pacific/Honolulu']);
    sendChatMessage($nuno, $mark);

    $this->actingAs($mark)->putJson(route('conversation-mute.update', $nuno), ['duration' => 'tomorrow'])
        ->assertOk()
        ->assertExactJson(['muted_until_at' => $expected]);

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.muted_until_at', $expected);
    $this->getJson(route('conversation-message.index', $nuno))->assertJsonPath('muted_until_at', $expected);
})->with([
    'local date ahead of UTC with fractional offset' => ['Asia/Kathmandu', '2026-01-01T20:00:00Z', '2026-01-02T18:15:00.000000Z'],
    'local date behind UTC' => ['America/Los_Angeles', '2026-01-02T02:00:00Z', '2026-01-02T08:00:00.000000Z'],
    'spring forward midnight is 23 hours away' => ['America/New_York', '2026-03-08T05:00:00Z', '2026-03-09T04:00:00.000000Z'],
    'fall back midnight is 25 hours away' => ['America/New_York', '2026-11-01T04:00:00Z', '2026-11-02T05:00:00.000000Z'],
    'no account timezone falls back to UTC' => [null, '2026-01-01T20:00:00Z', '2026-01-02T00:00:00.000000Z'],
]);

it('unmutes a conversation', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $this->actingAs($mark)->putJson(route('conversation-mute.update', $nuno), ['duration' => 'forever'])->assertOk();

    $this->putJson(route('conversation-mute.update', $nuno), ['duration' => 'off'])
        ->assertOk()
        ->assertExactJson(['muted_until_at' => null]);

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.muted_until_at', null);
    $this->getJson(route('conversation-message.index', $nuno))->assertJsonPath('muted_until_at', null);
});

it('stops showing a mute once it ends', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $this->actingAs($mark)->putJson(route('conversation-mute.update', $nuno), ['duration' => 'hour'])->assertOk();

    $this->travel(1)->hours();

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.muted_until_at', null);
    $this->getJson(route('conversation-message.index', $nuno))->assertJsonPath('muted_until_at', null);
});

it('keeps a mute through new messages without changing what is unread', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $muted = $this->actingAs($mark)->putJson(route('conversation-mute.update', $nuno), ['duration' => 'week'])->json('muted_until_at');
    $this->actingAs($mark)->postJson(route('conversation-archive.store', $nuno))->assertNoContent();

    sendChatMessage($nuno, $mark, 'one more thing');

    $this->actingAs($mark)->getJson(route('inbox.index'))
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('conversations.0.ahead', 2)
        ->assertJsonPath('conversations.0.is_archived', false)
        ->assertJsonPath('conversations.0.muted_until_at', $muted);
});

it('mutes an org for you only, and unmutes it', function (string $duration, string $expected): void {
    $this->travelTo(now()->parse('2026-01-01T20:00:00Z'));
    $mark = User::factory()->withRemotes()->create(['timezone' => 'Pacific/Honolulu']);
    [$nuno] = followersOf($mark, 1);
    $nuno->update(['timezone' => 'Asia/Kathmandu']);
    $org = createOrg($mark, [$nuno]);

    $this->actingAs($nuno)->putJson(route('org-mute.update', $org), ['duration' => $duration])
        ->assertOk()
        ->assertExactJson(['muted_until_at' => $expected]);

    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.muted_until_at', $expected);
    $this->getJson(route('org-message.index', $org))->assertJsonPath('muted_until_at', $expected);
    $this->actingAs($mark)->getJson(route('org-message.index', $org))->assertJsonPath('muted_until_at', null);

    $this->actingAs($nuno)->putJson(route('org-mute.update', $org), ['duration' => 'off'])->assertExactJson(['muted_until_at' => null]);

    $this->getJson(route('org-message.index', $org))->assertJsonPath('muted_until_at', null);
})->with([
    'an hour' => ['hour', '2026-01-01T21:00:00.000000Z'],
    'the members next local midnight, not the maintainers' => ['tomorrow', '2026-01-02T18:15:00.000000Z'],
]);

it('rejects durations it does not offer', function (array $payload): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $org = createOrg($mark, followersOf($mark, 1));

    $this->actingAs($mark)->putJson(route('conversation-mute.update', $nuno), $payload)->assertJsonValidationErrors('duration');
    $this->putJson(route('org-mute.update', $org), $payload)->assertJsonValidationErrors('duration');
})->with([
    'missing' => [[]],
    'empty' => [['duration' => '']],
    'unknown' => [['duration' => 'day']],
    'removed eight-hour option' => [['duration' => 'eight_hours']],
    'minutes' => [['duration' => 60]],
    'list' => [['duration' => ['hour']]],
]);

it('has nothing to mute before the conversation starts', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();

    $this->actingAs($mark)->putJson(route('conversation-mute.update', $nuno), ['duration' => 'hour'])->assertNotFound();

    expect(Conversation::query()->count())->toBe(0);
});

it('hides muting from people you cannot message', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    sendChatMessage($nuno, $mark, 'ship it');
    $nuno->blocking()->attach($mark);

    $this->actingAs($mark)->putJson(route('conversation-mute.update', $nuno), ['duration' => 'hour'])->assertNotFound();
});

it('hides muting an org from people outside it', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $org = createOrg($mark, followersOf($mark, 1));

    $this->actingAs(User::factory()->withRemotes()->create())
        ->putJson(route('org-mute.update', $org), ['duration' => 'hour'])
        ->assertNotFound();
    $this->actingAs($mark)->getJson(route('org-message.index', $org))->assertJsonPath('muted_until_at', null);
});

it('requires authentication', function (): void {
    $this->putJson(route('conversation-mute.update', User::factory()->withRemotes()->create()), ['duration' => 'hour'])->assertUnauthorized();
});
