<?php

declare(strict_types=1);

use App\Models\User;

it('shows the settings only the user sees', function (): void {
    $user = User::factory()->create([
        'timezone' => 'Europe/Budapest',
        'show_local_time' => true,
        'translation_language' => 'ja',
        'automatic_translation' => false,
    ]);

    $response = signedApiRequest(apiKeyPairFor($user), 'GET', route('api-user-settings.show'));

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.timezone', 'Europe/Budapest')
        ->assertJsonPath('data.show_local_time', true)
        ->assertJsonPath('data.translation_language', 'ja')
        ->assertJsonPath('data.automatic_translation', false)
        ->assertJsonPath('data.is_admin', false);
});

it('follows the device and translates automatically until the user chooses otherwise', function (): void {
    $response = signedApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', route('api-user-settings.show'));

    $response->assertOk()
        ->assertJsonPath('data.timezone', null)
        ->assertJsonPath('data.show_local_time', false)
        ->assertJsonPath('data.translation_language', null)
        ->assertJsonPath('data.automatic_translation', true);
});

it('lists the languages commits may be translated into, by their English name, each named in itself', function (): void {
    $languages = signedApiRequest(apiKeyPairFor(User::factory()->create()), 'GET', route('api-user-settings.show'))
        ->assertOk()
        ->json('data.translation_languages');

    $names = array_column($languages, 'name');
    $sorted = $names;
    sort($sorted);

    expect($languages)->toContain(['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語'])
        ->toContain(['code' => 'fr', 'name' => 'French', 'native_name' => 'français'])
        ->and($names)->toBe($sorted);
});

it('says whether the user is an admin', function (): void {
    config(['app.admins' => ['Octocat']]);

    signedApiRequest(apiKeyPairFor(User::factory()->create(['handle' => 'octocat'])), 'GET', route('api-user-settings.show'))
        ->assertOk()
        ->assertJsonPath('data.is_admin', true);
});

it('requires a signed request', function (): void {
    $this->getJson(route('api-user-settings.show'))->assertUnauthorized();
});
