<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\RemoteFollower;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('describes a user who federates as an ActivityPub actor', function (): void {
    $user = User::factory()->create([
        'username' => 'areinger',
        'name' => 'Ada Reinger',
        'bio' => "Ships <code>\nevery day",
        'avatar_url' => 'https://avatars.example/ada.png',
        'federates' => true,
    ]);

    $response = $this->getJson(route('actor.show', $user));

    $user->refresh();

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/activity+json')
        ->assertJson([
            '@context' => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1'],
            'id' => route('actor.show', $user),
            'type' => 'Person',
            'preferredUsername' => 'areinger',
            'name' => 'Ada Reinger',
            'summary' => "<p>Ships &lt;code&gt;<br>\nevery day</p>",
            'url' => route('user.show', $user),
            'icon' => ['type' => 'Image', 'url' => 'https://avatars.example/ada.png'],
            'inbox' => route('actor-inbox.store', $user),
            'outbox' => route('actor-outbox.index', $user),
            'followers' => route('actor-followers.index', $user),
            'publicKey' => [
                'id' => route('actor.show', $user).'#main-key',
                'owner' => route('actor.show', $user),
                'publicKeyPem' => $user->public_key,
            ],
        ]);

    expect($user->public_key)->toStartWith('-----BEGIN PUBLIC KEY-----')
        ->and($user->private_key)->toStartWith('-----BEGIN PRIVATE KEY-----');
});

it('keeps the private key encrypted at rest and the same across requests', function (): void {
    $user = User::factory()->create(['federates' => true]);

    $first = $this->getJson(route('actor.show', $user))->json('publicKey.publicKeyPem');
    $second = $this->getJson(route('actor.show', $user))->json('publicKey.publicKeyPem');

    expect($first)->toBe($second)
        ->and(DB::table('users')->where('id', $user->id)->value('private_key'))->not->toContain('PRIVATE KEY');
});

it('leaves out the summary and icon a user does not have', function (): void {
    $user = User::factory()->create(['bio' => null, 'avatar_url' => null, 'federates' => true]);

    $this->getJson(route('actor.show', $user))
        ->assertOk()
        ->assertJsonMissingPath('summary')
        ->assertJsonMissingPath('icon');
});

it('counts the remote followers', function (): void {
    $user = User::factory()->create(['federates' => true]);
    RemoteFollower::factory()->for($user)->count(2)->create();
    RemoteFollower::factory()->create();

    $this->getJson(route('actor-followers.index', $user))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/activity+json')
        ->assertExactJson([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => route('actor-followers.index', $user),
            'type' => 'OrderedCollection',
            'totalItems' => 2,
        ]);
});

it('counts only the commits that are sent to followers in the outbox', function (): void {
    $user = User::factory()->create(['federates' => true]);
    $sent = Commit::factory()->for($user)->create(['status' => CommitStatus::Approved]);
    Commit::factory()->for($user)->create(['status' => CommitStatus::Pending]);
    Commit::factory()->for($user)->create(['status' => CommitStatus::Approved, 'parent_id' => $sent->id]);
    Commit::factory()->for($user)->create(['status' => CommitStatus::Approved])->delete();

    $this->getJson(route('actor-outbox.index', $user))
        ->assertOk()
        ->assertJsonPath('id', route('actor-outbox.index', $user))
        ->assertJsonPath('totalItems', 1);
});

it('hides users who do not federate', function (string $route): void {
    $user = User::factory()->create();

    $this->getJson(route($route, $user))->assertNotFound();
})->with(['actor.show', 'actor-followers.index', 'actor-outbox.index']);
