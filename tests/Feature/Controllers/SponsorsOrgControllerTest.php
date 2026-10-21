<?php

declare(strict_types=1);

use App\Enums\SponsorsFrom;
use App\Models\Conversation;
use App\Models\MessageImage;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('starts a sponsors org, then goes to github for the sponsors', function (): void {
    $maintainer = User::factory()->create(['has_sponsors_listing' => true]);

    $this->actingAs($maintainer)
        ->post(route('sponsors-org.store'), ['from' => 'monthly'], ['X-Inertia' => 'true'])
        ->assertConflict()
        ->assertHeader('X-Inertia-Location', route('sponsorship-sync.create'));

    $org = Conversation::sponsorsOrgOf($maintainer);

    expect($org)
        ->name->toBe("@{$maintainer->username}'s sponsors")
        ->sponsors_from->toBe(SponsorsFrom::Monthly)
        ->and($org?->isMaintainedBy($maintainer))->toBeTrue();

    $this->get(route('user-profile.edit'))
        ->assertInertia(fn ($page) => $page->where('sponsorsOrg', ['id' => $org?->id, 'from' => 'monthly', 'sponsors' => 0]));
});

it('needs a sponsors listing and remotes, and only one org', function (array $attributes): void {
    $maintainer = User::factory()->create($attributes);

    $this->actingAs($maintainer)->post(route('sponsors-org.store'), ['from' => 'active'])->assertForbidden();
})->with([
    'no listing' => [['has_sponsors_listing' => false]],
    'remotes off' => [['has_sponsors_listing' => true, 'remotes_enabled' => false]],
]);

it('does not start a second sponsors org', function (): void {
    $maintainer = User::factory()->create(['has_sponsors_listing' => true]);
    createSponsorsOrg($maintainer);

    $this->actingAs($maintainer)->post(route('sponsors-org.store'), ['from' => 'active'])->assertForbidden();
});

it('changes who the org takes in', function (): void {
    $maintainer = User::factory()->create();
    $sponsor = User::factory()->create();
    Sponsorship::factory()->oneTime()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $sponsor->id]);
    $org = createSponsorsOrg($maintainer);

    $this->actingAs($maintainer)->put(route('sponsors-org.update'), ['from' => 'monthly'])->assertRedirect();

    expect($org->refresh()->sponsors_from)->toBe(SponsorsFrom::Monthly)
        ->and($org->membershipOf($sponsor))->toBeNull();

    $this->put(route('sponsors-org.update'), ['from' => 'nobody'])->assertSessionHasErrors('from');
});

it('turns the org off, forgetting the sponsors and erasing the images', function (): void {
    Storage::fake();
    $maintainer = User::factory()->create();
    $sponsor = User::factory()->create();
    $org = createSponsorsOrg($maintainer, [$sponsor]);
    $message = pushToOrg($maintainer, $org->id);
    Storage::put('message-images/shot.webp', 'image');
    MessageImage::factory()->create(['message_id' => $message->id, 'path' => 'message-images/shot.webp']);

    $this->actingAs($maintainer)->delete(route('sponsors-org.destroy'))->assertRedirect();

    expect(Conversation::query()->find($org->id))->toBeNull()
        ->and(Sponsorship::query()->count())->toBe(0);
    Storage::assertMissing('message-images/shot.webp');
});

it('has nothing to change or turn off without an org', function (): void {
    $this->actingAs(User::factory()->create())->put(route('sponsors-org.update'), ['from' => 'monthly'])->assertNotFound();
    $this->delete(route('sponsors-org.destroy'))->assertNotFound();
});
