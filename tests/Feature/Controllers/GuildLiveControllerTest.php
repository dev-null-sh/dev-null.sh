<?php

declare(strict_types=1);

use App\Actions\DeleteGuildModerator;
use App\Enums\LivePlatform;
use App\Enums\NotificationType;
use App\Jobs\EndLiveStreams;
use App\Models\Guild;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['app.admins' => ['admin', 'other-admin']]);
});

it('goes live with a YouTube stream for a few hours', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'jobs']);

    $this->actingAs($admin)->fromRoute('guild.show', $guild)
        ->put(route('guild-live.update', $guild), ['url' => ' https://youtu.be/dQw4w9WgXcQ ', 'hours' => 3])
        ->assertRedirectToRoute('guild.show', $guild);

    expect($guild->refresh())
        ->live_platform->toBe(LivePlatform::YouTube)
        ->live_url->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($guild->live_ends_at?->toIso8601String())->toBe(now()->addHours(3)->toIso8601String());

    $this->get(route('guild.show', $guild))
        ->assertInertia(fn ($page) => $page->where('live', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault_live.jpg',
            'ends_at' => $guild->live_ends_at?->toJSON(),
        ]));
});

it('goes live with a Twitch stream for a few hours', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'jobs']);

    $this->actingAs($admin)->fromRoute('guild.show', $guild)
        ->put(route('guild-live.update', $guild), ['url' => 'https://twitch.tv/octocat', 'hours' => 3])
        ->assertRedirectToRoute('guild.show', $guild);

    expect($guild->refresh())
        ->live_platform->toBe(LivePlatform::Twitch)
        ->live_url->toBe('https://www.twitch.tv/octocat');
});

it('rejects links that are not a stream', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->putJson(route('guild-live.update', $guild), ['url' => 'https://example.com/live', 'hours' => 2])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    expect($guild->refresh()->live_url)->toBeNull();
});

it('takes between one and twelve hours', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create(['user_id' => $admin->id]);

    $this->actingAs($admin)->putJson(route('guild-live.update', $guild), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'hours' => User::MAX_LIVE_HOURS + 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hours');

    expect($guild->refresh()->live_url)->toBeNull();
});

it('alerts members who joined and have not muted the guild', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $member = User::factory()->create();
    $muted = User::factory()->create();
    $guild = Guild::factory()->create(['user_id' => $admin->id, 'handle' => 'jobs', 'name' => 'Jobs']);
    DB::table('guild_preferences')->insert([
        ['guild_id' => $guild->id, 'user_id' => $admin->id, 'joined_at' => now(), 'muted_at' => null],
        ['guild_id' => $guild->id, 'user_id' => $member->id, 'joined_at' => now(), 'muted_at' => null],
        ['guild_id' => $guild->id, 'user_id' => $muted->id, 'joined_at' => now(), 'muted_at' => now()],
    ]);

    $this->actingAs($admin)->put(route('guild-live.update', $guild), [
        'url' => 'https://youtu.be/dQw4w9WgXcQ',
        'hours' => 2,
    ])->assertRedirect();

    $notification = Notification::query()->where('type', NotificationType::GuildLive)->sole();

    expect($notification->recipient_id)->toBe($member->id)
        ->and($notification->actor_id)->toBe($admin->id)
        ->and($notification->guild_id)->toBe($guild->id)
        ->and($notification->read_at)->toBeNull()
        ->and(Notification::query()->where('recipient_id', $member->id)->relevant()->sole()->is($notification))->toBeTrue();

    $notification->update(['read_at' => now()]);

    $this->actingAs($admin)->put(route('guild-live.update', $guild), [
        'url' => 'https://twitch.tv/octocat',
        'hours' => 2,
    ])->assertRedirect();

    expect(Notification::query()->where('type', NotificationType::GuildLive)->sole())
        ->recipient_id->toBe($member->id)
        ->read_at->toBeNull();
});

it('ends the stream', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create([
        'user_id' => $admin->id,
        'live_platform' => LivePlatform::YouTube,
        'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'live_ends_at' => now()->addHours(2),
    ]);

    $this->actingAs($admin)->fromRoute('guild.show', $guild)
        ->delete(route('guild-live.destroy', $guild))
        ->assertRedirectToRoute('guild.show', $guild);

    expect($guild->refresh())
        ->live_platform->toBeNull()
        ->live_url->toBeNull()
        ->live_ends_at->toBeNull();

    $this->get(route('guild.show', $guild))->assertInertia(fn ($page) => $page->where('live', null));
});

it('drops the member alert when the stream ends', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $member = User::factory()->create();
    $guild = Guild::factory()->create([
        'user_id' => $admin->id,
        'live_platform' => LivePlatform::YouTube,
        'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'live_ends_at' => now()->addHours(2),
    ]);
    Notification::factory()->for($member, 'recipient')->create([
        'type' => NotificationType::GuildLive,
        'actor_id' => $admin->id,
        'guild_id' => $guild->id,
    ]);

    $this->actingAs($admin)->delete(route('guild-live.destroy', $guild))->assertRedirect();

    expect(Notification::query()->where('type', NotificationType::GuildLive)->exists())->toBeFalse();
});

it('stops showing the stream once its hours are up', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $guild = Guild::factory()->create([
        'user_id' => $admin->id,
        'live_platform' => LivePlatform::YouTube,
        'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'live_ends_at' => now()->addHours(2),
    ]);

    $this->travel(2)->hours();

    $this->actingAs($admin)->get(route('guild.show', $guild))
        ->assertInertia(fn ($page) => $page->where('live', null));
});

it('does not let another admin point the guild at a stream', function (): void {
    $guild = Guild::factory()->create(['user_id' => User::factory()->create(['handle' => 'admin'])->id]);

    $this->actingAs(User::factory()->create(['handle' => 'other-admin']))
        ->putJson(route('guild-live.update', $guild), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'hours' => 2])
        ->assertNotFound();

    expect($guild->refresh()->live_url)->toBeNull();
});

it('requires authentication', function (): void {
    $guild = Guild::factory()->create();

    $this->putJson(route('guild-live.update', $guild), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'hours' => 2])->assertUnauthorized();
    $this->deleteJson(route('guild-live.destroy', $guild))->assertUnauthorized();
});

it('clears the stream when the guild loses its moderator', function (): void {
    $guild = Guild::factory()->create([
        'user_id' => User::factory()->create(['handle' => 'admin'])->id,
        'live_platform' => LivePlatform::Twitch,
        'live_url' => 'https://www.twitch.tv/octocat',
        'live_ends_at' => now()->addHour(),
    ]);

    resolve(DeleteGuildModerator::class)->handle($guild);

    expect($guild->refresh())
        ->user_id->toBeNull()
        ->live_url->toBeNull();
});

it('clears a guild stream that ran out', function (): void {
    $ended = Guild::factory()->create([
        'live_platform' => LivePlatform::YouTube,
        'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'live_ends_at' => now()->subMinute(),
    ]);
    $running = Guild::factory()->create([
        'live_platform' => LivePlatform::YouTube,
        'live_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'live_ends_at' => now()->addHour(),
    ]);
    $member = User::factory()->create();
    Notification::factory()->for($member, 'recipient')->create([
        'type' => NotificationType::GuildLive,
        'actor_id' => $ended->user_id,
        'guild_id' => $ended->id,
    ]);

    dispatch_sync(new EndLiveStreams());

    expect($ended->refresh()->live_url)->toBeNull()
        ->and($running->refresh()->liveStream()?->url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and(Notification::query()->where('guild_id', $ended->id)->exists())->toBeFalse();
});
