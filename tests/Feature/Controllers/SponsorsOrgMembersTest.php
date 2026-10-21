<?php

declare(strict_types=1);

use App\Actions\UpdateSponsorsOrgMembers;
use App\Enums\SponsorsFrom;
use App\Models\Message;
use App\Models\Sponsorship;
use App\Models\User;
use Illuminate\Support\Str;

it('keeps hidden sponsors out of what other sponsors see', function (): void {
    $maintainer = User::factory()->create();
    $hidden = User::factory()->create(['username' => 'hidden-sponsor']);
    $shown = User::factory()->create(['username' => 'shown-sponsor']);
    $org = createSponsorsOrg($maintainer, [$hidden, $shown]);
    $org->participants()->where('user_id', $shown->id)->update(['is_hidden' => false]);
    $this->actingAs($hidden)->putJson(route('org-typing.update', $org))->assertNoContent();

    $this->actingAs($shown)->getJson(route('org-message.index', $org))
        ->assertOk()
        ->assertJsonPath('members.*.user.username', [$maintainer->username, 'shown-sponsor'])
        ->assertJsonPath('members.*.sponsorship', [null, null])
        ->assertJsonPath('typing', [])
        ->assertJsonPath('sponsors', ['from' => 'active', 'hidden_count' => 1]);
    $this->getJson(route('inbox.index'))->assertJsonPath('conversations.0.members.*.user.username', [$maintainer->username, 'shown-sponsor']);
    $this->getJson(route('inbox.index', ['q' => 'hidden-sponsor']))->assertJsonPath('conversations', []);
    $this->getJson(route('mention-suggestion.index', ['q' => '', 'org' => $org]))->assertJsonPath('users.*.username', [$maintainer->username]);

    $this->actingAs($hidden)->getJson(route('org-message.index', $org))
        ->assertJsonPath('members.*.user.username', [$maintainer->username, 'hidden-sponsor', 'shown-sponsor'])
        ->assertJsonPath('members.1.is_hidden', true);
});

it('shows the maintainer every sponsor and how they sponsor', function (): void {
    $maintainer = User::factory()->create();
    [$monthly, $once, $lapsed] = User::factory()->count(3)->create();
    Sponsorship::factory()->oneTime()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $once->id]);
    Sponsorship::factory()->lapsed()->create(['sponsorable_id' => $maintainer->id, 'sponsor_id' => $lapsed->id]);
    $org = createSponsorsOrg($maintainer, [$monthly], from: SponsorsFrom::Everyone);

    $sponsorships = collect($this->actingAs($maintainer)->getJson(route('org-message.index', $org))->json('members'))
        ->mapWithKeys(fn (array $member): array => [$member['user']['id'] => $member['sponsorship']]);

    expect($sponsorships->all())->toEqual([
        $maintainer->id => null,
        $once->id => 'one-time',
        $lapsed->id => 'lapsed',
        $monthly->id => 'monthly',
    ]);
    $this->getJson(route('org-message.index', $org))->assertJsonPath('sponsors.hidden_count', 0);
});

it('lets a hidden sponsor push and react only once they show their membership', function (): void {
    $maintainer = User::factory()->create();
    $sponsor = User::factory()->create();
    $org = createSponsorsOrg($maintainer, [$sponsor]);
    $message = pushToOrg($maintainer, $org->id, 'welcome');

    $this->actingAs($sponsor)->postJson(route('org-message.store', $org), ['body' => 'hi', 'client_id' => Str::uuid()->toString()])
        ->assertForbidden()
        ->assertJsonPath('message', 'Show your membership to push.');
    $this->postJson(route('message-reaction.store', $message), ['emoji' => '😂'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Show your membership to react.');

    $this->putJson(route('org-visibility.update', $org), ['is_hidden' => false])->assertNoContent();

    pushToOrg($sponsor, $org->id, 'hi');
    $this->postJson(route('message-reaction.store', $message), ['emoji' => '😂'])->assertOk();
});

it('only lets sponsors hide', function (): void {
    $maintainer = User::factory()->create();
    [$member] = followersOf($maintainer, 1);
    $org = createSponsorsOrg($maintainer);

    $this->actingAs($maintainer)->putJson(route('org-visibility.update', $org), ['is_hidden' => true])->assertForbidden();
    $this->putJson(route('org-visibility.update', createOrg($maintainer, [$member])), ['is_hidden' => true])->assertForbidden();
    $this->actingAs($member)->putJson(route('org-visibility.update', $org), ['is_hidden' => true])->assertNotFound();
});

it('fills itself: nobody adds people, changes roles, or looks them up', function (): void {
    $maintainer = User::factory()->create();
    [$follower] = followersOf($maintainer, 1);
    $sponsor = User::factory()->create();
    $org = createSponsorsOrg($maintainer, [$sponsor]);

    $this->actingAs($maintainer)->postJson(route('org-member.store', $org), ['members' => [$follower->username], 'share_history' => true])->assertForbidden();
    $this->putJson(route('org-member.update', [$org, $sponsor]), ['role' => 'maintainer'])->assertForbidden();
    $this->getJson(route('remote-candidate.index', ['q' => $follower->username, 'org' => $org->id]))->assertForbidden();
});

it('answers the same whether or not someone is a hidden sponsor', function (): void {
    $maintainer = User::factory()->create();
    [$sponsor, $hidden, $outsider] = User::factory()->count(3)->create();
    $org = createSponsorsOrg($maintainer, [$sponsor, $hidden]);

    foreach ([$hidden, $outsider] as $member) {
        $this->actingAs($sponsor)->deleteJson(route('org-member.destroy', [$org, $member]))->assertForbidden();
        $this->putJson(route('org-member.update', [$org, $member]), ['role' => 'maintainer'])->assertForbidden();
    }
});

it('lets sponsors leave, quietly while hidden, and keeps them out', function (): void {
    $maintainer = User::factory()->create();
    [$hidden, $shown] = User::factory()->count(2)->create();
    $org = createSponsorsOrg($maintainer, [$hidden, $shown]);
    $org->participants()->where('user_id', $shown->id)->update(['is_hidden' => false]);
    $events = Message::query()->count();

    $this->actingAs($hidden)->deleteJson(route('org-member.destroy', [$org, $hidden]))->assertNoContent();

    expect(Message::query()->count())->toBe($events);

    $this->actingAs($maintainer)->deleteJson(route('org-member.destroy', [$org, $shown]))->assertNoContent();

    expect(Message::query()->latest('id')->firstOrFail()->subject_id)->toBe($shown->id)
        ->and(Sponsorship::query()->whereNotNull('left_at')->count())->toBe(2);

    resolve(UpdateSponsorsOrgMembers::class)->handle($org);

    expect($org->participants()->count())->toBe(1);
});

it('keeps its maintainer, who turns it off instead', function (): void {
    $maintainer = User::factory()->create();
    $org = createSponsorsOrg($maintainer, [User::factory()->create()]);

    $this->actingAs($maintainer)->deleteJson(route('org-member.destroy', [$org, $maintainer]))
        ->assertForbidden()
        ->assertJsonPath('message', 'Turn the sponsors org off in settings instead.');
});
