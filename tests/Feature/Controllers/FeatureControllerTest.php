<?php

declare(strict_types=1);

use App\Enums\Feature;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Guild;
use App\Models\User;
use App\Services\Features;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('lists the features to admins', function (): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('feature.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/features')
            ->where('features', [['name' => 'translations', 'enabled' => true, 'admin_only' => false], ['name' => 'guilds', 'enabled' => true, 'admin_only' => true]]));
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
        ->assertInertia(fn ($page) => $page->where('features', [['name' => 'translations', 'enabled' => true, 'admin_only' => false], ['name' => 'guilds', 'enabled' => true, 'admin_only' => true]]));

    $this->fromRoute('feature.index')
        ->put(route('feature.update', 'translations'), ['enabled' => false])
        ->assertRedirectToRoute('feature.index');

    expect(resolve(Features::class)->enabled(Feature::Translations))->toBeFalse();
    $this->assertDatabaseHas('features', ['name' => 'translations', 'enabled' => false]);
    $this->get(route('feature.index'))
        ->assertInertia(fn ($page) => $page->where('features', [['name' => 'translations', 'enabled' => false, 'admin_only' => false], ['name' => 'guilds', 'enabled' => true, 'admin_only' => true]]));

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

it('switches guild availability between everyone admins and nobody', function (): void {
    $admin = User::factory()->create(['handle' => 'admin']);
    $member = User::factory()->create();
    $guild = Guild::factory()->create();
    Event::fake([UsersUpdated::class]);

    $this->actingAs($member)->get(route('guild.show', $guild->handle))->assertNotFound();
    $this->actingAs($admin)->put(route('feature.update', Feature::Guilds), ['enabled' => true, 'admin_only' => false])->assertRedirect();
    $this->actingAs($member)->get(route('guild.show', $guild->handle))->assertOk();
    $this->post(route('guild.store'), ['name' => 'New Guild', 'description' => 'Open to everyone.', 'emoji' => '💻'])->assertRedirectToRoute('guild.show', 'newguild');
    $this->assertDatabaseHas('guilds', ['handle' => 'newguild', 'user_id' => $member->id]);
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Guilds && in_array($member->id, $event->userIds, true));
    Event::assertDispatched(UsersUpdated::class, fn (UsersUpdated $event): bool => $event->update === UserUpdate::Relationships && in_array($member->id, $event->userIds, true));

    $this->actingAs($admin)->put(route('feature.update', Feature::Guilds), ['enabled' => true, 'admin_only' => true])->assertRedirect();
    $this->get(route('guild.show', $guild->handle))->assertOk();
    $this->actingAs($member)->get(route('guild.show', $guild->handle))->assertNotFound();
    $this->actingAs($admin)->put(route('feature.update', Feature::Guilds), ['enabled' => false])->assertRedirect();
    $this->get(route('guild.show', $guild->handle))->assertNotFound();
    $this->actingAs($member)->get(route('guild.show', $guild->handle))->assertNotFound();
    $this->assertDatabaseHas('features', ['name' => 'guilds', 'enabled' => false, 'admin_only' => true]);
});

it('rejects an invalid audience without changing access', function (): void {
    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->putJson(route('feature.update', Feature::Guilds), ['enabled' => true, 'admin_only' => 'everyone'])
        ->assertUnprocessable()->assertJsonValidationErrors('admin_only');
    $this->assertDatabaseCount('features', 0);
});

it('keeps other admin-only features restricted to admins', function (): void {
    config(['app.admins' => ['admin'], 'app.moderators' => ['maude']]);
    $admin = User::factory()->create(['handle' => 'admin']);
    $moderator = User::factory()->create(['handle' => 'maude']);

    $this->actingAs($admin)->put(route('feature.update', Feature::Translations), ['enabled' => true, 'admin_only' => true])->assertRedirect();

    $features = app(Features::class);
    expect($features->available(Feature::Translations, $admin))->toBeTrue()
        ->and($features->available(Feature::Translations, $moderator))->toBeFalse()
        ->and($features->available(Feature::Guilds, $moderator))->toBeTrue()
        ->and($features->available(Feature::Guilds, $admin))->toBeTrue();
});
