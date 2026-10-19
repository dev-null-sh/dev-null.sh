<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Models\User;

function streamer(string $videoId, int $hoursLeft = 2): User
{
    return User::factory()->create([
        'live_platform' => LivePlatform::YouTube,
        'live_url' => "https://www.youtube.com/watch?v={$videoId}",
        'live_ends_at' => now()->addHours($hoursLeft),
    ]);
}

it('lists who is live, the stream with the most time left first', function (): void {
    $endingSoon = streamer('aaaaaaaaaaa', hoursLeft: 1);
    $endingLater = streamer('bbbbbbbbbbb', hoursLeft: 3);
    User::factory()->create();

    $response = $this->actingAs(User::factory()->create())->get(route('live.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('live/index')
            ->has('users.data', 2)
            ->where('users.data.0.id', $endingLater->id)
            ->where('users.data.0.live', [
                'url' => 'https://www.youtube.com/watch?v=bbbbbbbbbbb',
                'thumbnail_url' => 'https://i.ytimg.com/vi/bbbbbbbbbbb/hqdefault_live.jpg',
            ])
            ->where('users.data.1.id', $endingSoon->id)
            ->missing('users.data.0.email')
            ->missing('users.data.0.live_url')
            ->missing('users.data.0.live_ends_at'));
});

it('leaves out streams that ran out before the scheduler cleared them', function (): void {
    User::factory()->create([
        'live_platform' => LivePlatform::YouTube,
        'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'live_ends_at' => now()->subMinute(),
    ]);

    $this->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 0));
});

it('leaves out people the viewer muted or is blocked from', function (): void {
    $viewer = User::factory()->create();
    $muted = streamer('mmmmmmmmmmm');
    $blocked = streamer('kkkkkkkkkkk');
    $blocker = streamer('rrrrrrrrrrr');
    $visible = streamer('vvvvvvvvvvv');

    $viewer->muting()->attach($muted);
    $viewer->blocking()->attach($blocked);
    $blocker->blocking()->attach($viewer);

    $this->actingAs($viewer)->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $visible->id));
});

it('lists them to guests, and returns them here once they sign in', function (): void {
    streamer('dQw4w9WgXcQ');

    $this->get(route('live.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 1));

    expect(session('url.intended'))->toBe(route('live.index'));
});
