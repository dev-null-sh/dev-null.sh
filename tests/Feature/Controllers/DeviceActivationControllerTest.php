<?php

declare(strict_types=1);

use App\Actions\CreateDeviceActivation;
use App\Dto\DeviceAuthorization;
use App\Enums\DeviceAuthorizationStatus;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GitHubUser;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->fingerprint = 'SHA256:'.str_repeat('a', 43);
    $this->authorization = pendingDeviceAuthorization($this->fingerprint, 'devnull on a laptop')['authorization'];
});

it('asks the signed-in user for the code their terminal shows', function (): void {
    $response = $this->actingAs($this->user)->get(route('device-activation.show'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('device-activation/show')
            ->missing('authorization')
            ->missing('error')
            ->missing('done')
            ->where('auth.user.id', $this->user->id));
});

it('asks the user to approve the device behind the code', function (): void {
    $response = $this->actingAs($this->user)->get(route('device-activation.show', ['user_code' => $this->authorization->userCode]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('device-activation/show')
            ->where('authorization', [
                'user_code' => $this->authorization->userCode,
                'device_name' => 'devnull on a laptop',
                'fingerprint' => $this->fingerprint,
            ])
            ->where('csrfToken', session()->token())
            ->missing('error')
            ->where('auth.user.id', $this->user->id));
});

it('reads the code however it was typed', function (Closure $typed): void {
    $response = $this->actingAs($this->user)->get(route('device-activation.show', ['user_code' => $typed($this->authorization->userCode)]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->where('authorization.user_code', $this->authorization->userCode));
})->with([
    'in lowercase' => mb_strtolower(...),
    'without the dash' => fn (string $code): string => str_replace('-', '', $code),
    'with spaces' => fn (string $code): string => ' '.str_replace('-', ' ', $code).' ',
]);

it('sends a guest through the github sign-in, then back to the code', function (): void {
    Queue::fake();
    fakeGitHubSponsorsListing();

    $this->get(route('device-activation.show', ['user_code' => $this->authorization->userCode]))->assertRedirectToRoute('home');

    $user = User::factory()->create(['github_id' => '583231']);

    Socialite::fake('github', GitHubUser::fake([
        'id' => '583231',
        'nickname' => $user->username,
    ]));

    $location = (string) $this->get(route('login.store'))->assertRedirect()->headers->get('Location');

    expect($location)->toBe(route('device-activation.show', ['user_code' => $this->authorization->userCode]));

    $this->assertAuthenticatedAs($user);
});

it('refuses a code it cannot find, and asks for it again', function (Closure $request, string $error, ?string $userCode): void {
    $response = $request($this);

    $response->assertBadRequest()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('device-activation/show')
            ->where('error', $error)
            ->where('userCode', $userCode ?? $this->authorization->userCode)
            ->missing('authorization')
            ->missing('done'));
})->with([
    'an unknown code' => [
        fn ($test): TestResponse => $test->actingAs($test->user)->get(route('device-activation.show', ['user_code' => 'bcdf-ghjk'])),
        'This code is unknown or has expired. Check your terminal, and start the sign-in again if you need to.',
        'BCDF-GHJK',
    ],
    'a malformed code' => [
        fn ($test): TestResponse => $test->actingAs($test->user)->get(route('device-activation.show', ['user_code' => 'bcd'])),
        'A code is eight letters, like WDJB-MJHT.',
        'BCD',
    ],
    'a code that is not text' => [
        fn ($test): TestResponse => $test->actingAs($test->user)->get(route('device-activation.show', ['user_code' => ['BCDF-GHJK']])),
        'The user code field must be a string.',
        '',
    ],
    'an expired code' => [
        function ($test): TestResponse {
            $test->travel(DeviceAuthorization::EXPIRES_AFTER_MINUTES)->minutes();

            return $test->actingAs($test->user)->get(route('device-activation.show', ['user_code' => $test->authorization->userCode]));
        },
        'This code is unknown or has expired. Check your terminal, and start the sign-in again if you need to.',
        null,
    ],
    'a code already approved' => [
        function ($test): TestResponse {
            resolve(CreateDeviceActivation::class)->handle($test->user, $test->authorization, DeviceAuthorizationStatus::Approved);

            return $test->actingAs($test->user)->get(route('device-activation.show', ['user_code' => $test->authorization->userCode]));
        },
        'This code is unknown or has expired. Check your terminal, and start the sign-in again if you need to.',
        null,
    ],
    'a code already denied' => [
        function ($test): TestResponse {
            resolve(CreateDeviceActivation::class)->handle($test->user, $test->authorization, DeviceAuthorizationStatus::Denied);

            return $test->actingAs($test->user)->get(route('device-activation.show', ['user_code' => $test->authorization->userCode]));
        },
        'This code is unknown or has expired. Check your terminal, and start the sign-in again if you need to.',
        null,
    ],
]);

it('approves the device once the user says so, and tells them their terminal is signing in', function (): void {
    $response = $this->actingAs($this->user)->post(route('device-activation.store'), ['user_code' => $this->authorization->userCode, 'decision' => 'approved']);

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('device-activation/show')
            ->where('done', ['status' => 'approved', 'device_name' => 'devnull on a laptop'])
            ->missing('authorization')
            ->missing('error'));

    $device = Device::query()->sole();

    expect($device->user_id)->toBe($this->user->id)
        ->and($device->fingerprint)->toBe($this->fingerprint)
        ->and($device->name)->toBe('devnull on a laptop')
        ->and($device->approved_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and(Cache::get($this->authorization->key))->toBe([...$this->authorization->toArray(), 'status' => 'approved', 'user_id' => $this->user->id])
        ->and(Cache::has(DeviceAuthorization::userCodeKeyFor($this->authorization->userCode)))->toBeFalse();
});

it('denies the device when the user says so', function (): void {
    $response = $this->actingAs($this->user)->post(route('device-activation.store'), ['user_code' => mb_strtolower($this->authorization->userCode), 'decision' => 'denied']);

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('device-activation/show')
            ->where('done', ['status' => 'denied', 'device_name' => 'devnull on a laptop'])
            ->missing('authorization'));

    expect(Device::query()->exists())->toBeFalse()
        ->and(Cache::get($this->authorization->key))->toBe([...$this->authorization->toArray(), 'status' => 'denied', 'user_id' => null])
        ->and(Cache::has(DeviceAuthorization::userCodeKeyFor($this->authorization->userCode)))->toBeFalse();
});

it('decides nothing for a code it cannot find, or without a decision', function (Closure $request, string $error): void {
    $response = $request($this);

    $response->assertBadRequest()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('device-activation/show')
            ->where('error', $error)
            ->missing('authorization')
            ->missing('done'));

    expect(Device::query()->exists())->toBeFalse()
        ->and(Cache::get($this->authorization->key))->toMatchArray(['status' => 'pending', 'user_id' => null]);
})->with([
    'an unknown code' => [
        fn ($test): TestResponse => $test->actingAs($test->user)->post(route('device-activation.store'), ['user_code' => 'BCDF-GHJK', 'decision' => 'approved']),
        'This code is unknown or has expired. Check your terminal, and start the sign-in again if you need to.',
    ],
    'a malformed code' => [
        fn ($test): TestResponse => $test->actingAs($test->user)->post(route('device-activation.store'), ['user_code' => 'bcd', 'decision' => 'approved']),
        'A code is eight letters, like WDJB-MJHT.',
    ],
    'no code' => [
        fn ($test): TestResponse => $test->actingAs($test->user)->post(route('device-activation.store'), ['decision' => 'approved']),
        'The user code field is required.',
    ],
    'a code that is not text' => [
        fn ($test): TestResponse => $test->actingAs($test->user)->post(route('device-activation.store'), ['user_code' => ['BCDF-GHJK'], 'decision' => 'approved']),
        'The user code field must be a string.',
    ],
    'no decision' => [
        fn ($test): TestResponse => $test->actingAs($test->user)->post(route('device-activation.store'), ['user_code' => $test->authorization->userCode]),
        'The decision field is required.',
    ],
    'a decision that is not one' => [
        fn ($test): TestResponse => $test->actingAs($test->user)->post(route('device-activation.store'), ['user_code' => $test->authorization->userCode, 'decision' => 'pending']),
        'The selected decision is invalid.',
    ],
]);

it('decides nothing for a code that expired while the page was open', function (): void {
    $this->travel(DeviceAuthorization::EXPIRES_AFTER_MINUTES)->minutes();

    $this->actingAs($this->user)->post(route('device-activation.store'), ['user_code' => $this->authorization->userCode, 'decision' => 'approved'])
        ->assertBadRequest()
        ->assertInertia(fn (Assert $page): Assert => $page->where('error', 'This code is unknown or has expired. Check your terminal, and start the sign-in again if you need to.'));

    expect(Device::query()->exists())->toBeFalse();
});

it('decides a code only once', function (): void {
    $this->actingAs($this->user)->post(route('device-activation.store'), ['user_code' => $this->authorization->userCode, 'decision' => 'denied'])->assertOk();

    $this->actingAs($this->user)->post(route('device-activation.store'), ['user_code' => $this->authorization->userCode, 'decision' => 'approved'])
        ->assertBadRequest()
        ->assertInertia(fn (Assert $page): Assert => $page->where('error', 'This code is unknown or has expired. Check your terminal, and start the sign-in again if you need to.'));

    expect(Device::query()->exists())->toBeFalse()
        ->and(Cache::get($this->authorization->key))->toMatchArray(['status' => 'denied']);
});

it('decides only for a signed-in user', function (): void {
    $this->get(route('device-activation.show', ['user_code' => $this->authorization->userCode]))->assertRedirectToRoute('home');
    $this->post(route('device-activation.store'), ['user_code' => $this->authorization->userCode, 'decision' => 'approved'])->assertRedirectToRoute('home');

    expect(Device::query()->exists())->toBeFalse()
        ->and(Cache::get($this->authorization->key))->toMatchArray(['status' => 'pending']);
});

it('lets each user look a code up or decide 20 times a minute', function (): void {
    foreach (range(1, 20) as $attempt) {
        $this->actingAs($this->user)->get(route('device-activation.show', ['user_code' => 'BCDF-GHJK']))->assertBadRequest();
    }

    $this->actingAs($this->user)->get(route('device-activation.show', ['user_code' => $this->authorization->userCode]))->assertTooManyRequests();
    $this->actingAs($this->user)->post(route('device-activation.store'), ['user_code' => $this->authorization->userCode, 'decision' => 'approved'])->assertTooManyRequests();

    $this->actingAs(User::factory()->create())->get(route('device-activation.show', ['user_code' => $this->authorization->userCode]))->assertOk();

    expect(Device::query()->exists())->toBeFalse();
});
