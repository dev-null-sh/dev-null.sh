<?php

declare(strict_types=1);

use App\Enums\OrganizationRole;
use App\Events\ProfilesUpdated;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('hides the people of an organization until its admins show them', function (): void {
    expect(Organization::factory()->create()->refresh()->shows_people)->toBeFalse();
});

it('lets an admin show and hide the people of the organization, updating its page live', function (bool $showsPeople, string $message): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['handle' => 'laravel', 'shows_people' => ! $showsPeople]);
    $organization->admins()->attach($user->id);
    Event::fake([ProfilesUpdated::class]);

    $this->actingAs($user)
        ->from('/@laravel')
        ->put(route('organization-people-visibility.update', $organization), ['shows_people' => $showsPeople])
        ->assertRedirect('/@laravel')
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => $message]);

    expect($organization->refresh()->shows_people)->toBe($showsPeople);
    Event::assertDispatched(ProfilesUpdated::class);
})->with([
    'show' => [true, 'People are shown on the page.'],
    'hide' => [false, 'People are hidden from the page.'],
]);

it('requires a yes or no', function (array $input): void {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $organization->admins()->attach($user->id);

    $this->actingAs($user)
        ->put(route('organization-people-visibility.update', $organization), $input)
        ->assertSessionHasErrors('shows_people');

    expect($organization->refresh()->shows_people)->toBeFalse();
})->with([
    'missing' => [[]],
    'not a boolean' => [['shows_people' => 'maybe']],
]);

it('hides the setting from members who are not admins, and from everyone else', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->members()->attach($member->id, ['role' => OrganizationRole::Member]);

    $this->actingAs($member)
        ->put(route('organization-people-visibility.update', $organization), ['shows_people' => true])
        ->assertNotFound();
    $this->actingAs(User::factory()->create())
        ->put(route('organization-people-visibility.update', $organization), ['shows_people' => true])
        ->assertNotFound();

    expect($organization->refresh()->shows_people)->toBeFalse();
});

it('requires authentication', function (): void {
    $organization = Organization::factory()->create();

    $this->put(route('organization-people-visibility.update', $organization), ['shows_people' => true])
        ->assertRedirectToRoute('home');
});
