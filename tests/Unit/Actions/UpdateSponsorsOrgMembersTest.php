<?php

declare(strict_types=1);

use App\Actions\UpdateSponsorsOrgMembers;
use App\Enums\SponsorsFrom;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Sponsorship;
use App\Models\User;

it('takes in the sponsors the org is set to', function (SponsorsFrom $from, array $expected): void {
    $maintainer = User::factory()->create();
    $org = createSponsorsOrg($maintainer, from: $from);
    $sponsors = collect(['monthly', 'one-time', 'lapsed monthly', 'lapsed one-time', 'left'])->mapWithKeys(fn (string $kind): array => [$kind => User::factory()->create()]);

    Sponsorship::factory()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $sponsors['monthly']->id]);
    Sponsorship::factory()->oneTime()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $sponsors['one-time']->id]);
    Sponsorship::factory()->lapsed()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $sponsors['lapsed monthly']->id]);
    Sponsorship::factory()->oneTime()->lapsed()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $sponsors['lapsed one-time']->id]);
    Sponsorship::factory()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $sponsors['left']->id, 'left_at' => now()]);

    resolve(UpdateSponsorsOrgMembers::class)->handle($org);

    expect($sponsors->filter(fn (User $sponsor): bool => $org->membershipOf($sponsor) instanceof ConversationParticipant)->keys()->all())->toBe($expected);
})->with([
    'monthly sponsors' => [SponsorsFrom::Monthly, ['monthly']],
    'active sponsors' => [SponsorsFrom::Active, ['monthly', 'one-time']],
    'everyone who sponsored' => [SponsorsFrom::Everyone, ['monthly', 'one-time', 'lapsed monthly', 'lapsed one-time']],
]);

it('lets people in hidden and out quietly, never across a block', function (): void {
    $maintainer = User::factory()->create();
    [$sponsor, $blocked] = User::factory()->count(2)->create();
    $maintainer->blocking()->attach($blocked);
    $org = createSponsorsOrg($maintainer, [$sponsor, $blocked]);
    $events = Message::query()->count();

    expect($org->membershipOf($sponsor)?->is_hidden)->toBeTrue()
        ->and($org->membershipOf($blocked))->toBeNull();

    Sponsorship::query()->where('sponsor_id', $sponsor->id)->update(['lapses_at' => now()->subDay()]);
    resolve(UpdateSponsorsOrgMembers::class)->handle($org);

    expect($org->membershipOf($sponsor))->toBeNull()
        ->and(Message::query()->count())->toBe($events);
});
