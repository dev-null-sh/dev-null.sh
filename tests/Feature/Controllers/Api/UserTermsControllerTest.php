<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Date;

it('says whether the person accepted the terms as they stand', function (): void {
    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    signedApiRequest($keyPair, 'GET', route('api-current-user.show'))
        ->assertOk()
        ->assertJsonPath('data.terms_version', config('legal.terms_version'))
        ->assertJsonPath('data.terms_accepted_at', null)
        ->assertJsonPath('data.terms_accepted', false);

    $user->update(['terms_accepted_at' => '2026-10-24 23:59:59']);

    signedApiRequest($keyPair, 'GET', route('api-current-user.show'))->assertJsonPath('data.terms_accepted', false);
});

it('records that the terms were accepted, and answers as the current user endpoint does', function (): void {
    Date::setTestNow('2026-10-25 12:00:00');

    $user = User::factory()->create();
    $keyPair = apiKeyPairFor($user);

    $response = signedApiRequest($keyPair, 'PUT', route('api-user-terms.update'), ['version' => config('legal.terms_version')]);

    $response->assertOk()
        ->assertJsonPath('data.terms_accepted', true)
        ->assertJsonPath('data.terms_accepted_at', '2026-10-25T12:00:00Z')
        ->assertExactJson(signedApiRequest($keyPair, 'GET', route('api-current-user.show'))->json());

    expect($user->refresh()->hasAcceptedCurrentTerms())->toBeTrue();
});

it('only accepts the version in force, so an old build cannot accept terms it never showed', function (mixed $version): void {
    $user = User::factory()->create();

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-user-terms.update'), $version === null ? [] : ['version' => $version])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('version');

    expect($user->refresh()->terms_accepted_at)->toBeNull();
})->with([null, '2020-01-01', '2026-10-24']);

it('rejects requests that are not signed', function (): void {
    $this->putJson(route('api-user-terms.update'), ['version' => config('legal.terms_version')])->assertUnauthorized();
});
