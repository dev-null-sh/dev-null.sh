<?php

declare(strict_types=1);

use App\Contracts\Services\LastFmProvider;
use App\Dto\Track;
use App\Enums\Feature;
use App\Models\User;
use App\Services\Features;
use Illuminate\Http\Client\ConnectionException;

beforeEach(function (): void {
    resolve(Features::class)->update(Feature::NowPlaying, true);
});

it('links a Last.fm account', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->fromRoute('user.show', $user)
        ->put(route('user-now-playing.update'), ['lastfm_username' => ' RJ '])
        ->assertRedirectToRoute('user.show', $user);

    expect($user->refresh()->lastfm_username)->toBe('RJ');
});

it('unlinks the Last.fm account', function (): void {
    $user = User::factory()->create(['lastfm_username' => 'rj']);

    $this->actingAs($user)->fromRoute('user.show', $user)
        ->delete(route('user-now-playing.destroy'))
        ->assertRedirectToRoute('user.show', $user);

    expect($user->refresh()->lastfm_username)->toBeNull();
});

it('rejects usernames Last.fm could not have', function (mixed $username): void {
    $this->mock(LastFmProvider::class)->shouldNotReceive('exists');
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-now-playing.update'), ['lastfm_username' => $username])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lastfm_username');

    expect($user->refresh()->lastfm_username)->toBeNull();
})->with([
    'missing' => [null],
    'not a string' => [['rj']],
    'too short' => ['r'],
    'too long' => ['abcdefghijklmnop'],
    'starts with a number' => ['1rj'],
    'a link' => ['https://www.last.fm/user/rj'],
]);

it('rejects a username nobody has on Last.fm', function (): void {
    $this->mock(LastFmProvider::class)->shouldReceive('exists')->with('nobody')->andReturnFalse();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-now-playing.update'), ['lastfm_username' => 'nobody'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lastfm_username' => 'There is no Last.fm account with that username.']);
});

it('asks to try again when Last.fm is not answering', function (): void {
    $this->mock(LastFmProvider::class)->shouldReceive('exists')->andThrow(new ConnectionException('Timed out'));
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-now-playing.update'), ['lastfm_username' => 'rj'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lastfm_username' => "Last.fm isn't answering. Try again in a moment."]);
});

it('cannot link an account until the feature is switched on', function (): void {
    resolve(Features::class)->update(Feature::NowPlaying, false);
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-now-playing.update'), ['lastfm_username' => 'rj'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lastfm_username' => "Sharing what you're listening to isn't available yet."]);

    expect($user->refresh()->lastfm_username)->toBeNull();
});

it('requires authentication to link or unlink an account', function (): void {
    $this->putJson(route('user-now-playing.update'), ['lastfm_username' => 'rj'])->assertUnauthorized();
    $this->deleteJson(route('user-now-playing.destroy'))->assertUnauthorized();
});

it('shows anyone what a member is listening to', function (): void {
    $this->mock(LastFmProvider::class)->shouldReceive('nowPlaying')->with('rj')->andReturn(new Track('Daft Punk', 'Digital Love', 'Discovery', 'https://www.last.fm/music/Daft+Punk/_/Digital+Love'));
    $user = User::factory()->create(['lastfm_username' => 'rj']);

    $this->getJson(route('user-now-playing.show', $user))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['track' => [
            'artist' => 'Daft Punk',
            'title' => 'Digital Love',
            'album' => 'Discovery',
            'url' => 'https://www.last.fm/music/Daft+Punk/_/Digital+Love',
        ]]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('user-now-playing.show', $user))
        ->assertJsonPath('track.title', 'Digital Love');
});

it('shows nothing playing for a member without a linked account', function (): void {
    $this->getJson(route('user-now-playing.show', User::factory()->create()))
        ->assertOk()
        ->assertExactJson(['track' => null]);
});

it('shows nothing playing between members with a block', function (Closure $block): void {
    $this->mock(LastFmProvider::class)->shouldNotReceive('nowPlaying');
    $viewer = User::factory()->create();
    $user = User::factory()->create(['lastfm_username' => 'rj']);
    $block($viewer, $user);

    $this->actingAs($viewer)
        ->getJson(route('user-now-playing.show', $user))
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertExactJson(['track' => null]);
})->with([
    'you blocked them' => fn (User $viewer, User $user) => $viewer->blocking()->attach($user),
    'they blocked you' => fn (User $viewer, User $user) => $user->blocking()->attach($viewer),
]);
