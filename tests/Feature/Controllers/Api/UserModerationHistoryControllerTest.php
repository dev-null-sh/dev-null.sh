<?php

declare(strict_types=1);

use App\Enums\ModerationReason;
use App\Models\BanEvent;
use App\Models\User;
use App\Models\UserTimeout;

it('shows the user their timeouts and bans by GitHub account, newest first, as their profile does', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
    UserTimeout::factory()->create([
        'github_id' => '583231',
        'reason' => ModerationReason::LowEffort,
        'created_at' => now()->subDays(3),
        'ends_at' => now()->subDays(2),
    ]);
    UserTimeout::factory()->create([
        'github_id' => '583231',
        'reason' => ModerationReason::Spam,
        'created_at' => now()->subHours(2),
        'ends_at' => now()->addHour(),
    ]);
    BanEvent::factory()->create([
        'github_id' => '583231',
        'reason' => ModerationReason::Spam,
        'created_at' => now()->subWeek(),
    ]);
    BanEvent::factory()->create([
        'github_id' => '583231',
        'reason' => ModerationReason::LowEffort,
        'created_at' => now()->subMonth(),
    ]);
    // Recorded before this account existed, as when someone deletes their account and comes back.
    $user = User::factory()->create(['github_id' => '583231']);

    $response = signedApiRequest(apiKeyPairFor($user), 'GET', route('api-user-moderation-history.show'));

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.timeouts_count', 2)
        ->assertJsonPath('data.bans_count', 2)
        ->assertJsonPath('data.timeouts.0.reason', 'Spam or self-promotion')
        ->assertJsonPath('data.timeouts.0.duration', '3 hours')
        ->assertJsonPath('data.timeouts.0.started_at', '2026-11-13T10:00:00Z')
        ->assertJsonPath('data.timeouts.0.ends_at', '2026-11-13T13:00:00Z')
        ->assertJsonPath('data.timeouts.1.reason', 'Low-effort posts')
        ->assertJsonPath('data.timeouts.1.duration', '1 day')
        ->assertJsonPath('data.bans.0.reason', 'Spam or self-promotion')
        ->assertJsonPath('data.bans.0.banned_at', '2026-11-06T12:00:00Z')
        ->assertJsonPath('data.bans.1.reason', 'Low-effort posts');

    expect($response->json('data.timeouts.0'))->toHaveKeys(['id', 'reason', 'duration', 'started_at', 'ends_at'])
        ->and($response->json('data.bans.0'))->toHaveKeys(['id', 'reason', 'banned_at']);
});

it('shows a clean history, never anyone else’s', function (): void {
    UserTimeout::factory()->create();
    BanEvent::factory()->create();

    signedApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', route('api-user-moderation-history.show'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'timeouts_count' => 0,
            'bans_count' => 0,
            'timeouts' => [],
            'bans' => [],
        ]]);
});

it('stays closed to a banned user, so it only ever lists lifted bans', function (): void {
    $user = User::factory()->create(['banned_at' => now()]);

    signedApiRequest(apiKeyPairFor($user), 'GET', route('api-user-moderation-history.show'))
        ->assertForbidden()
        ->assertJsonPath('code', 'banned');
});

it('requires a signed request', function (): void {
    $this->getJson(route('api-user-moderation-history.show'))->assertUnauthorized();
});
