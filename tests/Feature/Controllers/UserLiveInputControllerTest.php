<?php

declare(strict_types=1);

use App\Enums\LivePlatform;
use App\Models\User;
use App\Models\UserTimeout;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.cloudflare_stream' => [
        'account_id' => 'account',
        'api_token' => 'token',
        'customer_subdomain' => 'customer-abc.cloudflarestream.com',
    ]]);
});

function liveInputResponse(string $uid): array
{
    return ['result' => [
        'uid' => $uid,
        'rtmps' => ['url' => 'rtmps://live.cloudflare.com:443/live/', 'streamKey' => "key-{$uid}"],
    ]];
}

it('makes a live input the first time and flashes its stream key', function (): void {
    $user = User::factory()->create(['handle' => 'octocat']);
    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account/stream/live_inputs' => Http::response(['result' => ['uid' => 'input-1']]),
        'api.cloudflare.com/client/v4/accounts/account/stream/live_inputs/input-1' => Http::response(liveInputResponse('input-1')),
    ]);

    $this->actingAs($user)->fromRoute('user.show', $user)
        ->post(route('user-live-input.store'))
        ->assertRedirectToRoute('user.show', $user)
        ->assertInertiaFlash('liveInput', [
            'rtmps_url' => 'rtmps://live.cloudflare.com:443/live/',
            'stream_key' => 'key-input-1',
        ]);

    expect($user->refresh()->live_input_id)->toBe('input-1');
});

it('keeps the same stream key between streams', function (): void {
    $user = User::factory()->create(['live_input_id' => 'input-1']);
    Http::fake(['api.cloudflare.com/client/v4/accounts/account/stream/live_inputs/input-1' => Http::response(liveInputResponse('input-1'))]);

    $this->actingAs($user)->post(route('user-live-input.store'))
        ->assertInertiaFlash('liveInput.stream_key', 'key-input-1');

    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
});

it('makes a new live input when the old one was deleted on Cloudflare', function (): void {
    $user = User::factory()->create(['live_input_id' => 'gone']);
    Http::fake([
        'api.cloudflare.com/client/v4/accounts/account/stream/live_inputs/gone' => Http::response(status: 404),
        'api.cloudflare.com/client/v4/accounts/account/stream/live_inputs' => Http::response(['result' => ['uid' => 'input-2']]),
        'api.cloudflare.com/client/v4/accounts/account/stream/live_inputs/input-2' => Http::response(liveInputResponse('input-2')),
    ]);

    $this->actingAs($user)->post(route('user-live-input.store'))
        ->assertInertiaFlash('liveInput.stream_key', 'key-input-2');

    expect($user->refresh()->live_input_id)->toBe('input-2');
});

it('goes live on the live input for a few hours, playing it right on /dev/null', function (): void {
    $user = User::factory()->create(['live_input_id' => 'input-1']);

    $this->actingAs($user)->fromRoute('user.show', $user)
        ->put(route('user-live-input.update'), ['hours' => 3])
        ->assertRedirectToRoute('user.show', $user);

    expect($user->refresh())
        ->live_platform->toBe(LivePlatform::Cloudflare)
        ->live_url->toBe('https://customer-abc.cloudflarestream.com/input-1/iframe')
        ->and($user->live_ends_at?->toIso8601String())->toBe(now()->addHours(3)->toIso8601String());

    $this->get(route('user.show', $user))
        ->assertInertia(fn ($page) => $page
            ->where('user.live', [
                'url' => 'https://customer-abc.cloudflarestream.com/input-1/iframe',
                'thumbnail_url' => 'https://customer-abc.cloudflarestream.com/input-1/thumbnails/thumbnail.jpg',
                'embed_url' => 'https://customer-abc.cloudflarestream.com/input-1/iframe',
            ])
            ->missing('user.live_input_id'));

    Http::assertNothingSent();
});

it('does not go live during a timeout, as a link cannot either', function (): void {
    $user = User::factory()->create(['live_input_id' => 'input-1']);
    UserTimeout::factory()->for($user)->create();

    $this->actingAs($user)->putJson(route('user-live-input.update'), ['hours' => 3])
        ->assertForbidden();

    expect($user->refresh()->live_url)->toBeNull();
});

it('needs a stream key before going live', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('user-live-input.update'), ['hours' => 2])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hours' => 'Get your stream key first.']);

    expect($user->refresh()->live_url)->toBeNull();
});

it('takes between one and twelve hours', function (mixed $hours): void {
    $user = User::factory()->create(['live_input_id' => 'input-1']);

    $this->actingAs($user)->putJson(route('user-live-input.update'), ['hours' => $hours])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hours');

    expect($user->refresh()->live_url)->toBeNull();
})->with([
    'missing' => null,
    'none' => 0,
    'too many' => User::MAX_LIVE_HOURS + 1,
]);

it('is not found while Cloudflare Stream is not set up', function (): void {
    config(['services.cloudflare_stream.api_token' => null]);
    $user = User::factory()->create(['live_input_id' => 'input-1']);

    $this->actingAs($user)->postJson(route('user-live-input.store'))->assertNotFound();
    $this->actingAs($user)->putJson(route('user-live-input.update'), ['hours' => 2])->assertNotFound();

    expect($user->refresh()->live_url)->toBeNull();
});

it('offers streaming here on your own profile only, once Cloudflare Stream is set up', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('user.show', $user))
        ->assertInertia(fn ($page) => $page->where('canStreamHere', true));

    $this->actingAs(User::factory()->create())->get(route('user.show', $user))
        ->assertInertia(fn ($page) => $page->where('canStreamHere', false));

    config(['services.cloudflare_stream.api_token' => null]);

    $this->actingAs($user)->get(route('user.show', $user))
        ->assertInertia(fn ($page) => $page->where('canStreamHere', false));
});

it('requires authentication', function (): void {
    $this->postJson(route('user-live-input.store'))->assertUnauthorized();
    $this->putJson(route('user-live-input.update'), ['hours' => 2])->assertUnauthorized();
});
