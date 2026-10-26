<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\User;
use App\Services\Features;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('lists the features to admins', function (): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('feature.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/features')
            ->where('features', [['name' => 'translations', 'enabled' => true]])
            ->where('hasTraffic', false));
});

it('offers the traffic tab when a Fathom token is configured', function (): void {
    config(['services.fathom.token' => 'token', 'services.fathom.site_id' => 'SITE']);

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('feature.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hasTraffic', true));
});

it('hides the features from non admins', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('feature.index'))
        ->assertNotFound();
});

it('requires authentication to list the features', function (): void {
    $this->get(route('feature.index'))->assertRedirectToRoute('home');
});

it('lets admins switch a feature off and back on for everyone', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);

    $this->actingAs($admin)->get(route('feature.index'))
        ->assertInertia(fn ($page) => $page->where('features', [['name' => 'translations', 'enabled' => true]]));

    $this->fromRoute('feature.index')
        ->put(route('feature.update', 'translations'), ['enabled' => false])
        ->assertRedirectToRoute('feature.index');

    expect(resolve(Features::class)->enabled(Feature::Translations))->toBeFalse();
    $this->assertDatabaseHas('features', ['name' => 'translations', 'enabled' => false]);
    $this->get(route('feature.index'))
        ->assertInertia(fn ($page) => $page->where('features', [['name' => 'translations', 'enabled' => false]]));

    $this->put(route('feature.update', 'translations'), ['enabled' => true])->assertRedirect();

    expect(resolve(Features::class)->enabled(Feature::Translations))->toBeTrue();
    $this->assertDatabaseCount('features', 1);
    $this->assertDatabaseHas('features', ['name' => 'translations', 'enabled' => true]);
});

it('rejects missing or invalid settings without changing the feature', function (array $payload): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->putJson(route('feature.update', 'translations'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('enabled');

    expect(resolve(Features::class)->enabled(Feature::Translations))->toBeTrue();
    $this->assertDatabaseCount('features', 0);
})->with([fn (): array => [], fn (): array => ['enabled' => 'yes'], fn (): array => ['enabled' => null]]);

it('is a 404 for unknown features', function (): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->put(route('feature.update', 'teleportation'), ['enabled' => false])
        ->assertNotFound();

    $this->assertDatabaseCount('features', 0);
});

it('is a 404 for non admins', function (): void {
    $this->actingAs(User::factory()->create())
        ->put(route('feature.update', 'translations'), ['enabled' => false])
        ->assertNotFound();

    expect(resolve(Features::class)->enabled(Feature::Translations))->toBeTrue();
    $this->assertDatabaseCount('features', 0);
});

it('requires authentication', function (): void {
    $this->putJson(route('feature.update', 'translations'), ['enabled' => false])->assertUnauthorized();
});
