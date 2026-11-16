<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('saves automatic protection privately for the authenticated user', function (bool $enabled): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->fromRoute('user-profile.edit')->put(route('user-streak-shields.update'), [
        'enabled' => $enabled, 'user_id' => $other->id,
    ])->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Automatic streak protection saved.']);

    expect($user->refresh()->automatic_streak_shields)->toBe($enabled)
        ->and($other->refresh()->automatic_streak_shields)->toBeTrue()
        ->and($user->toArray())->not->toHaveKey('automatic_streak_shields');

    $this->assertDatabaseHas('streak_shield_preferences', [
        'user_id' => $user->id, 'effective_on' => today()->toDateString(), 'enabled' => $enabled,
    ]);
    $this->get(route('user-profile.edit'))->assertInertia(fn ($page) => $page
        ->where('auth.automaticStreakShields', $enabled)->missing('auth.user.automatic_streak_shields'));
})->with([true, false]);

it('rejects invalid choices without saving history', function (array $data): void {
    $user = User::factory()->create();
    $this->actingAs($user)->fromRoute('user-profile.edit')->put(route('user-streak-shields.update'), $data)
        ->assertSessionHasErrors('enabled');
    expect($user->refresh()->automatic_streak_shields)->toBeTrue()
        ->and(DB::table('streak_shield_preferences')->count())->toBe(0);
})->with(['missing' => [[]], 'null' => [['enabled' => null]], 'text' => [['enabled' => 'invalid']]]);

it('requires a signed-in user', function (): void {
    $this->put(route('user-streak-shields.update'), ['enabled' => false])->assertRedirectToRoute('home');
});

it('saves the same choice through the signed API and exposes it only in private settings', function (): void {
    $user = User::factory()->create();
    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-user-streak-shields.update'), ['enabled' => false])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.automatic_streak_shields', false);
    signedApiRequest(apiKeyPairFor($user), 'GET', route('api-user-settings.show'))
        ->assertOk()->assertJsonPath('data.automatic_streak_shields', false);
    $this->putJson(route('api-user-streak-shields.update'), ['enabled' => true])->assertUnauthorized();
});
