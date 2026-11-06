<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Models\User;
use Illuminate\Support\Str;

function liveOn(User $user, ?int $minutes = 60): User
{
    $user->forceFill([
        'live_platform' => LivePlatform::Twitch,
        'live_url' => 'https://www.twitch.tv/'.$user->handle,
        'live_ends_at' => $minutes === null ? null : now()->addMinutes($minutes),
    ])->save();

    return $user;
}

it('says which of the people a page shows are live right now, for anyone', function (): void {
    $live = liveOn(User::factory()->create(['handle' => 'streamer']));
    $ended = liveOn(User::factory()->create(), -1);
    $quiet = User::factory()->create();
    $missing = (string) Str::uuid();

    $response = $this->getJson(route('live-stream.index', ['ids' => [$live->id, $ended->id, $quiet->id, $missing]]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($response->json("streams.{$live->id}"))->toBe([
        'url' => 'https://www.twitch.tv/streamer',
        'thumbnail_url' => 'https://static-cdn.jtvnw.net/previews-ttv/live_user_streamer-1280x720.jpg',
        'ends_at' => $live->live_ends_at?->toJSON(),
    ])
        ->and($response->json('streams'))->toHaveKeys([$live->id, $ended->id, $quiet->id, $missing])
        ->and($response->json("streams.{$ended->id}"))->toBeNull()
        ->and($response->json("streams.{$quiet->id}"))->toBeNull()
        ->and($response->json("streams.{$missing}"))->toBeNull();
});

it('answers not live for people across a block, either way', function (): void {
    $viewer = User::factory()->create();
    $blocked = liveOn(User::factory()->create());
    $blocker = liveOn(User::factory()->create());
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    $streams = $this->actingAs($viewer)->getJson(route('live-stream.index', ['ids' => [$blocked->id, $blocker->id]]))->json('streams');

    expect($streams[$blocked->id])->toBeNull()->and($streams[$blocker->id])->toBeNull();
});

it('needs a bounded list of ids', function (): void {
    $this->getJson(route('live-stream.index'))->assertUnprocessable()->assertJsonValidationErrors('ids');
    $this->getJson(route('live-stream.index', ['ids' => array_map(fn (): string => (string) Str::uuid(), range(1, 101))]))
        ->assertUnprocessable()->assertJsonValidationErrors('ids');
});
