<?php

declare(strict_types=1);

use App\Enums\OrgEvent;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('creates an org with people who follow the maintainer', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor] = followersOf($mark, 2);

    $org = createOrg($mark, [$nuno, $taylor]);

    expect(Conversation::query()->findOrFail($org)->roleOf($mark)?->value)->toBe('maintainer')
        ->and(Conversation::query()->findOrFail($org)->roleOf($nuno)?->value)->toBe('contributor')
        ->and(Message::query()->sole()->event)->toBe(OrgEvent::Created);

    $this->actingAs($nuno)->getJson(route('inbox.index'))
        ->assertJsonPath('conversations.0.kind', 'org')
        ->assertJsonPath('conversations.0.name', 'dev/null core')
        ->assertJsonCount(3, 'conversations.0.members')
        ->assertJsonPath('conversations.0.members.0.role', 'maintainer');
});

it('identifies orgs by uuids', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);

    $org = createOrg($mark, [$nuno]);

    expect($org)->toBeUuid();

    $this->actingAs($mark)->get('/remotes/orgs/'.$org)->assertOk();
    $this->actingAs($mark)->get('/remotes/orgs/1')->assertNotFound();
});

it('says why people cannot be added', function (string $case, string $reason): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $candidate = User::factory()->create(['username' => 'candidate']);

    if ($case !== 'not a follower') {
        $candidate->following()->attach($mark);
    }

    match ($case) {
        'remotes off' => $candidate->update(['remotes_enabled' => false]),
        'blocked by someone joining' => $nuno->blocking()->attach($candidate),
        'blocked by the maintainer' => $mark->blocking()->attach($candidate),
        default => null,
    };

    $this->actingAs($mark)->postJson(route('org.store'), ['members' => ['candidate', $nuno->username]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['members.0' => "@candidate {$reason}."]);
})->with([
    ['not a follower', "doesn't follow you"],
    ['remotes off', 'has remotes off'],
    ['blocked by someone joining', 'has a block with someone in the org'],
    ['blocked by the maintainer', 'has a block with someone in the org'],
]);

it('lets site admins add anyone with remotes on', function (): void {
    config(['app.admins' => ['mark']]);
    $mark = User::factory()->create(['username' => 'mark']);
    $nuno = User::factory()->create();

    createOrg($mark, [$nuno]);

    $this->actingAs($nuno)->getJson(route('inbox.index'))->assertJsonCount(1, 'conversations');
});

it('caps an org at a hundred people', function (): void {
    $mark = User::factory()->create();

    $this->actingAs($mark)->postJson(route('org.store'), ['members' => array_map(fn (User $user): string => $user->username, followersOf($mark, Conversation::MAX_MEMBERS))])
        ->assertUnprocessable()->assertJsonValidationErrors('members');
});

it('shows an org only to its members', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    pushToOrg($nuno, $org, 'standup in 10?');

    $this->actingAs($mark)->get(route('org.show', $org))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('conversation/index')
            ->where('org.name', 'dev/null core')
            ->where('thread.role', 'maintainer')
            ->has('thread.members', 2)
            ->where('thread.messages.1.body', 'standup in 10?')
            ->where('thread.messages.1.user.username', $nuno->username));

    $this->actingAs(User::factory()->create())->get(route('org.show', $org))->assertNotFound();
    $this->getJson(route('org-message.index', $org))->assertNotFound();
});

it('polls the badge counts on an open org without marking it read', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno]);
    pushToOrg($nuno, $org, 'standup in 10?');

    $this->actingAs($mark)->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) new HandleInertiaRequests()->version(request()),
        'X-Inertia-Partial-Component' => 'conversation/index',
        'X-Inertia-Partial-Data' => 'auth.unreadNotificationsCount,auth.unreadMessagesCount',
    ])->get(route('org.show', $org))->assertOk()
        ->assertJsonPath('props.auth.unreadMessagesCount', 1)
        ->assertJsonMissingPath('props.thread');

    $this->actingAs(User::factory()->create())->get(route('org.show', $org))->assertNotFound();
});

it('lets maintainers rename the org', function (): void {
    $mark = User::factory()->create();
    [$nuno] = followersOf($mark, 1);
    $org = createOrg($mark, [$nuno], name: null);

    $this->actingAs($nuno)->putJson(route('org.update', $org), ['name' => 'nope'])->assertForbidden();
    $this->actingAs($mark)->putJson(route('org.update', $org), ['name' => ' polling crew '])->assertNoContent();

    expect(Conversation::query()->findOrFail($org)->name)->toBe('polling crew')
        ->and(Message::query()->latest('id')->firstOrFail()->event)->toBe(OrgEvent::Renamed);
});

it('requires remotes and authentication', function (): void {
    $this->postJson(route('org.store'), ['members' => ['x']])->assertUnauthorized();
    $this->actingAs(User::factory()->create(['remotes_enabled' => false]))->postJson(route('org.store'), ['members' => ['x']])->assertNotFound();
});
