<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\Organization;
use App\Models\User;

it('returns a summary of the organization for its preview', function (): void {
    $viewer = User::factory()->create();
    $organization = Organization::factory()->create();

    $organization->followers()->attach([$viewer->id, User::factory()->create()->id]);

    $this->actingAs($viewer)
        ->getJson("/mentions/organizations/{$organization->id}/preview")
        ->assertOk()
        ->assertExactJsonStructure(['commitsCount', 'followersCount', 'isFollowing'])
        ->assertJsonPath('followersCount', 2)
        ->assertJsonPath('isFollowing', true);
});

it('counts only the organization approved commits', function (): void {
    $organization = Organization::factory()->create();

    Commit::factory()->count(2)->create(['author_id' => $organization->id]);
    Commit::factory()->pending()->create(['author_id' => $organization->id]);
    Commit::factory()->create(['author_id' => $organization->id])->delete();
    Commit::factory()->create();

    $this->getJson("/mentions/organizations/{$organization->id}/preview")
        ->assertOk()
        ->assertJsonPath('commitsCount', 2);
});

it('reports when the viewer does not follow the organization', function (bool $guest): void {
    $organization = Organization::factory()->create();

    $organization->followers()->attach(User::factory()->create());

    ($guest ? $this : $this->actingAs(User::factory()->create()))
        ->getJson("/mentions/organizations/{$organization->id}/preview")
        ->assertOk()
        ->assertJsonPath('followersCount', 1)
        ->assertJsonPath('isFollowing', false);
})->with(['guest' => true, 'member' => false]);

it('returns not found for unknown organizations', function (): void {
    $this->getJson('/mentions/organizations/'.fake()->uuid().'/preview')
        ->assertNotFound();
});
