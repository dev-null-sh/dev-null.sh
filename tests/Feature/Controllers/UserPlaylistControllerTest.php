<?php

declare(strict_types=1);

use App\Contracts\Services\PlaylistProvider;
use App\Enums\UserUpdate;
use App\Events\ProfilesUpdated;
use App\Events\UsersUpdated;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;

it('shares a playlist from Audius', function (): void {
    Event::fake([ProfilesUpdated::class, UsersUpdated::class]);
    $this->mock(PlaylistProvider::class)->shouldReceive('tracks')->with('https://audius.co/Byte/playlist/lofi')->andReturn([]);
    $user = User::factory()->create();

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->put(route('user-playlist.update'), ['playlist_url' => ' https://audius.co/Byte/playlist/lofi '])
        ->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->playlist_url)->toBe('https://audius.co/Byte/playlist/lofi');

    Event::assertDispatched(ProfilesUpdated::class, fn (ProfilesUpdated $event): bool => $event->profileIds === [$user->id]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->userIds === [$user->id] && $event->update === UserUpdate::Account);
});

it('stops sharing the playlist', function (): void {
    Event::fake([ProfilesUpdated::class, UsersUpdated::class]);
    $user = User::factory()->create(['playlist_url' => 'https://audius.co/Byte/playlist/lofi']);

    $this->actingAs($user)->fromRoute('user-profile.edit')
        ->delete(route('user-playlist.destroy'))
        ->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->playlist_url)->toBeNull();

    Event::assertDispatched(ProfilesUpdated::class, fn (ProfilesUpdated $event): bool => $event->profileIds === [$user->id]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->userIds === [$user->id] && $event->update === UserUpdate::Account);
});

it('rejects a link that is not to a playlist', function (): void {
    $this->mock(PlaylistProvider::class)->shouldReceive('tracks')->with('https://audius.co/broeybeats/paradise')->andReturnNull();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-playlist.update'), ['playlist_url' => 'https://audius.co/broeybeats/paradise'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['playlist_url' => 'Use a link to a public playlist or album on Audius.']);

    expect($user->refresh()->playlist_url)->toBeNull();
});

it('rejects links without asking Audius when they cannot be one', function (mixed $url): void {
    $this->mock(PlaylistProvider::class)->shouldNotReceive('tracks');

    $this->actingAs(User::factory()->create())->putJson(route('user-playlist.update'), ['playlist_url' => $url])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('playlist_url');
})->with([
    'missing' => [null],
    'not a string' => [['https://audius.co/Byte/playlist/lofi']],
    'too long' => ['https://audius.co/Byte/playlist/'.str_repeat('a', 240)],
]);

it('asks to try again when Audius is not answering', function (): void {
    $this->mock(PlaylistProvider::class)->shouldReceive('tracks')->andThrow(new ConnectionException('Timed out'));

    $this->actingAs(User::factory()->create())->putJson(route('user-playlist.update'), ['playlist_url' => 'https://audius.co/Byte/playlist/lofi'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['playlist_url' => "Audius isn't answering. Try again in a moment."]);
});

it('requires authentication to share or stop sharing a playlist', function (): void {
    $this->putJson(route('user-playlist.update'), ['playlist_url' => 'https://audius.co/Byte/playlist/lofi'])->assertUnauthorized();
    $this->deleteJson(route('user-playlist.destroy'))->assertUnauthorized();
});
