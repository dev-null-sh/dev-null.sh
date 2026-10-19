<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Models\User;
use App\Services\Features;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('lets admins switch a feature off and back on for everyone', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);

    $this->actingAs($admin)->get(route('operations.show'))
        ->assertInertia(fn ($page) => $page->where('features', [['name' => 'translations', 'enabled' => true]]));

    $this->fromRoute('operations.show')
        ->put(route('feature.update', 'translations'), ['enabled' => false])
        ->assertRedirectToRoute('operations.show');

    expect(resolve(Features::class)->enabled(Feature::Translations))->toBeFalse();
    $this->assertDatabaseHas('features', ['name' => 'translations', 'enabled' => false]);
    $this->get(route('operations.show'))
        ->assertInertia(fn ($page) => $page->where('features', [['name' => 'translations', 'enabled' => false]]));

    $this->put(route('feature.update', 'translations'), ['enabled' => true])->assertRedirect();

    expect(resolve(Features::class)->enabled(Feature::Translations))->toBeTrue();
    $this->assertDatabaseCount('features', 1);
    $this->assertDatabaseHas('features', ['name' => 'translations', 'enabled' => true]);
});

it('rejects missing or invalid settings without changing the feature', function (array $payload): void {
    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->putJson(route('feature.update', 'translations'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('enabled');

    expect(resolve(Features::class)->enabled(Feature::Translations))->toBeTrue();
    $this->assertDatabaseCount('features', 0);
})->with([fn (): array => [], fn (): array => ['enabled' => 'yes'], fn (): array => ['enabled' => null]]);

it('is a 404 for unknown features', function (): void {
    $this->actingAs(User::factory()->create(['username' => 'admin']))
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
