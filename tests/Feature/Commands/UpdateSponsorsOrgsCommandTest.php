<?php

declare(strict_types=1);

use App\Enums\SponsorsFrom;
use App\Models\Sponsorship;
use App\Models\User;

it('lets one-time sponsors out once their thirty days are up, unless the org takes everyone', function (): void {
    [$active, $everyone] = User::factory()->count(2)->create();
    $sponsor = User::factory()->create();
    Sponsorship::factory()->oneTime()->create(['sponsorable_id' => $active->id, 'sponsor_id' => $sponsor->id]);
    Sponsorship::factory()->oneTime()->create(['sponsorable_id' => $everyone->id, 'sponsor_id' => $sponsor->id]);
    $activeOrg = createSponsorsOrg($active);
    $everyoneOrg = createSponsorsOrg($everyone, from: SponsorsFrom::Everyone);

    $this->travel(Sponsorship::ONE_TIME_DAYS + 1)->days();
    $this->artisan('sponsors:update-orgs')->assertSuccessful();

    expect($activeOrg->membershipOf($sponsor))->toBeNull()
        ->and($everyoneOrg->membershipOf($sponsor))->not->toBeNull();
});
