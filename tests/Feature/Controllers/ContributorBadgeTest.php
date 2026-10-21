<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

it('shows the contributor achievement on public profiles without exposing its storage flag', function (bool $signedIn): void {
    $user = User::factory()->create(['is_contributor' => true]);

    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get(route('user.show', $user))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('user/show')
            ->missing('user.is_contributor')
            ->where('achievements.badges', fn ($badges): bool => collect($badges)->contains(
                fn ($badge): bool => $badge['id'] === 'contributor' && $badge['unlocked'] === true,
            )));

    Http::assertNothingSent();
})->with(['guest' => false, 'signed in' => true]);

it('includes the contributor achievement in signed api profiles', function (): void {
    $user = User::factory()->create(['is_contributor' => true]);
    $keyPair = apiKeyPairFor(User::factory()->create());

    $response = signedApiRequest($keyPair, 'GET', route('api-user.show', $user));

    $response->assertOk()->assertJsonMissingPath('data.is_contributor');

    expect(collect($response->json('achievements.badges'))->firstWhere('id', 'contributor'))
        ->toMatchArray(['unlocked' => true, 'progress' => 1, 'target' => 1]);

    Http::assertNothingSent();
});
