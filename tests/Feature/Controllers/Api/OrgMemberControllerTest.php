<?php

declare(strict_types=1);

use App\Enums\OrgEvent;
use App\Enums\OrgRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserTimeout;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('adds people with or without the history, as the web does', function (bool $sharesHistory, int $visible): void {
    [$nuno, $taylor] = followersOf($this->user, 2);
    $org = createOrg($this->user, [$nuno]);
    pushToOrg($this->user, $org, 'before taylor joined');

    signedApiRequest($this->keyPair, 'POST', route('api-org-member.store', $org), ['members' => [$taylor->handle], 'share_history' => $sharesHistory])
        ->assertNoContent();

    $messages = collect($this->actingAs($taylor)->getJson(route('org-message.index', $org))->json('messages'));

    expect($messages->whereNull('event')->count())->toBe($visible)
        ->and($messages->last()['event'])->toBe('added');
})->with([
    'with the history' => [true, 1],
    'from now on' => [false, 0],
]);

it('says why people cannot be added', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    User::factory()->create(['handle' => 'stranger']);

    signedApiRequest($this->keyPair, 'POST', route('api-org-member.store', $org), ['members' => ['stranger'], 'share_history' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['members.0' => "@stranger doesn't follow you."]);
    signedApiRequest($this->keyPair, 'POST', route('api-org-member.store', $org), ['members' => ['nobody-by-that-name'], 'share_history' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('members.0');
    signedApiRequest($this->keyPair, 'POST', route('api-org-member.store', $org), ['members' => [$nuno->handle]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('share_history');
});

it('caps an org at a hundred people', function (): void {
    $followers = followersOf($this->user, Conversation::MAX_MEMBERS);
    [$last, $extra] = array_slice($followers, -2);
    $org = createOrg($this->user, array_slice($followers, 0, -2));

    signedApiRequest($this->keyPair, 'POST', route('api-org-member.store', $org), ['members' => [$last->handle, $extra->handle], 'share_history' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('members');
    signedApiRequest($this->keyPair, 'POST', route('api-org-member.store', $org), ['members' => [$last->handle], 'share_history' => true])
        ->assertNoContent();
});

it('lets only maintainers add, change roles, and remove others', function (): void {
    $mark = User::factory()->create();
    $this->user->following()->attach($mark);
    [$nuno, $taylor] = followersOf($mark, 2);
    $org = createOrg($mark, [$this->user, $nuno]);

    signedApiRequest($this->keyPair, 'POST', route('api-org-member.store', $org), ['members' => [$taylor->handle], 'share_history' => true])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can add people.');
    signedApiRequest($this->keyPair, 'PUT', route('api-org-member.update', [$org, $nuno->handle]), ['role' => 'maintainer'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can change roles.');
    signedApiRequest($this->keyPair, 'DELETE', route('api-org-member.destroy', [$org, $nuno->handle]))
        ->assertForbidden()
        ->assertJsonPath('message', 'Only maintainers can remove people.');

    expect(Conversation::query()->findOrFail($org)->roleOf($nuno))->toBe(OrgRole::Contributor);
});

it('promotes and demotes, keeping at least one maintainer', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-member.update', [$org, $this->user->handle]), ['role' => 'contributor'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role' => 'An org needs at least one maintainer.']);
    signedApiRequest($this->keyPair, 'PUT', route('api-org-member.update', [$org, $nuno->handle]), ['role' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
    signedApiRequest($this->keyPair, 'PUT', route('api-org-member.update', [$org, $nuno->handle]), ['role' => 'maintainer'])->assertNoContent();

    expect(Message::query()->latest('id')->firstOrFail()->event)->toBe(OrgEvent::Promoted);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-member.update', [$org, $this->user->handle]), ['role' => 'contributor'])->assertNoContent();

    $org = Conversation::query()->findOrFail($org);

    expect($org->roleOf($this->user))->toBe(OrgRole::Contributor)
        ->and($org->roleOf($nuno))->toBe(OrgRole::Maintainer)
        ->and(Message::query()->latest('id')->firstOrFail()->event)->toBe(OrgEvent::Demoted);
});

it('removes people, who then lose access', function (): void {
    [$nuno, $taylor] = followersOf($this->user, 2);
    $org = createOrg($this->user, [$nuno, $taylor]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-org-member.destroy', [$org, $taylor->handle]))->assertNoContent();

    expect(Message::query()->latest('id')->firstOrFail()->event)->toBe(OrgEvent::Removed);
    $this->actingAs($taylor)->getJson(route('org-message.index', $org))->assertNotFound();
});

it('lets anyone leave', function (): void {
    $mark = User::factory()->create();
    $this->user->following()->attach($mark);
    $org = createOrg($mark, [$this->user]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-org-member.destroy', [$org, $this->user->handle]))->assertNoContent();

    expect(Message::query()->latest('id')->firstOrFail()->event)->toBe(OrgEvent::Left);
    signedApiRequest($this->keyPair, 'GET', route('api-org-message.index', $org))->assertNotFound();
});

it('hides membership changes from people outside the org', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    $outsider = User::factory()->create();

    signedApiRequest($this->keyPair, 'POST', route('api-org-member.store', $org), ['members' => [$nuno->handle], 'share_history' => true])->assertNotFound();
    signedApiRequest($this->keyPair, 'PUT', route('api-org-member.update', [$org, $nuno->handle]), ['role' => 'maintainer'])->assertNotFound();
    signedApiRequest($this->keyPair, 'DELETE', route('api-org-member.destroy', [$org, $nuno->handle]))->assertNotFound();

    [$own] = followersOf($this->user, 1);
    $mine = createOrg($this->user, [$own]);

    signedApiRequest($this->keyPair, 'PUT', route('api-org-member.update', [$mine, $outsider->handle]), ['role' => 'maintainer'])->assertNotFound();
    signedApiRequest($this->keyPair, 'DELETE', route('api-org-member.destroy', [$mine, $outsider->handle]))->assertNotFound();

    $this->user->update(['remotes_enabled' => false]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-org-member.destroy', [$mine, $own->handle]))->assertNotFound();
});

it('refuses adding people during a timeout', function (): void {
    [$nuno, $taylor] = followersOf($this->user, 2);
    $org = createOrg($this->user, [$nuno]);
    UserTimeout::factory()->for($this->user)->create();

    signedApiRequest($this->keyPair, 'POST', route('api-org-member.store', $org), ['members' => [$taylor->handle], 'share_history' => true])
        ->assertForbidden()
        ->assertJsonPath('code', 'timed_out');
});

it('rejects requests that are not signed', function (): void {
    [$nuno] = followersOf($this->user, 1);
    $org = createOrg($this->user, [$nuno]);
    auth()->forgetGuards();

    $this->deleteJson(route('api-org-member.destroy', [$org, $nuno->handle]))->assertUnauthorized();
});
