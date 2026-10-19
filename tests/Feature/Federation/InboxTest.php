<?php

declare(strict_types=1);

use App\Contracts\Services\DnsResolver;
use App\Jobs\DeliverActivity;
use App\Models\RemoteFollower;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Support\RemoteActor;

beforeEach(function (): void {
    Queue::fake();

    $this->app->instance(DnsResolver::class, new readonly class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['mastodon.example' => ['93.184.216.34'], 'internal.example' => ['10.0.0.5']][$host] ?? [];
        }
    });

    $this->remote = RemoteActor::generate();
    $this->user = User::factory()->create(['username' => 'areinger', 'federates' => true]);

    Http::fake([$this->remote->id => Http::response($this->remote->document())]);
});

/**
 * @param  array<string, mixed>  $activity
 * @param  array<string, string>  $headers
 */
function deliverToInbox(User $user, RemoteActor $remote, array $activity, array $headers = []): TestResponse
{
    $body = json_encode($activity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $path = parse_url(route('actor-inbox.store', $user), PHP_URL_PATH);
    $url = parse_url(config()->string('app.url'));
    $host = $url['host'].(isset($url['port']) ? ':'.$url['port'] : '');

    $server = ['CONTENT_TYPE' => 'application/activity+json'];

    foreach ([...$remote->sign($path, $host, $body), ...$headers] as $name => $value) {
        $server['HTTP_'.mb_strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('POST', route('actor-inbox.store', $user), server: $server, content: $body);
}

it('lets a Mastodon account follow a user and accepts the follow', function (): void {
    $follow = $this->remote->follow(route('actor.show', $this->user));

    deliverToInbox($this->user, $this->remote, $follow)->assertStatus(202);

    $follower = RemoteFollower::query()->sole();

    expect($follower->user_id)->toBe($this->user->id)
        ->and($follower->actor_url)->toBe($this->remote->id)
        ->and($follower->inbox_url)->toBe($this->remote->id.'/inbox')
        ->and($follower->shared_inbox_url)->toBe('https://mastodon.example/inbox');

    Queue::assertPushed(DeliverActivity::class, fn (DeliverActivity $job): bool => $job->user->is($this->user)
        && $job->inbox === $this->remote->id.'/inbox'
        && $job->activity['type'] === 'Accept'
        && $job->activity['actor'] === route('actor.show', $this->user)
        && $job->activity['object'] === $follow);
});

it('fetches the follower signed as the user they follow, as servers in authorized fetch mode require', function (): void {
    deliverToInbox($this->user, $this->remote, $this->remote->follow(route('actor.show', $this->user)))->assertStatus(202);

    $this->user->refresh();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === $this->remote->id
        && str_contains($request->header('Signature')[0], 'keyId="'.route('actor.show', $this->user).'#main-key"')
        && str_contains($request->header('Signature')[0], 'headers="(request-target) host date"')
        && RemoteActor::verifies((string) $this->user->public_key, 'GET', '/users/alice', $request->headers()));
});

it('accepts signatures labelled hs2019, as newer Mastodon versions send', function (): void {
    $follow = $this->remote->follow(route('actor.show', $this->user));
    $body = json_encode($follow, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $path = parse_url(route('actor-inbox.store', $this->user), PHP_URL_PATH);
    $url = parse_url(config()->string('app.url'));
    $host = $url['host'].(isset($url['port']) ? ':'.$url['port'] : '');

    deliverToInbox($this->user, $this->remote, $follow, $this->remote->sign($path, $host, $body, algorithm: 'hs2019'))->assertStatus(202);

    expect(RemoteFollower::query()->count())->toBe(1);
});

it('keeps one follower when the same account follows twice', function (): void {
    $follow = $this->remote->follow(route('actor.show', $this->user));

    deliverToInbox($this->user, $this->remote, $follow)->assertStatus(202);
    deliverToInbox($this->user, $this->remote, $follow)->assertStatus(202);

    expect(RemoteFollower::query()->count())->toBe(1);
});

it('removes the follower when the follow is undone', function (): void {
    RemoteFollower::factory()->for($this->user)->create(['actor_url' => $this->remote->id]);
    RemoteFollower::factory()->for($this->user)->create();

    deliverToInbox($this->user, $this->remote, [
        'id' => $this->remote->id.'#follows/1/undo',
        'type' => 'Undo',
        'actor' => $this->remote->id,
        'object' => $this->remote->follow(route('actor.show', $this->user)),
    ])->assertStatus(202);

    expect($this->user->remoteFollowers()->where('actor_url', $this->remote->id)->exists())->toBeFalse()
        ->and($this->user->remoteFollowers()->count())->toBe(1);
});

it('ignores undoing anything but a follow of this user', function (array $object): void {
    RemoteFollower::factory()->for($this->user)->create(['actor_url' => $this->remote->id]);

    deliverToInbox($this->user, $this->remote, [
        'type' => 'Undo',
        'actor' => $this->remote->id,
        'object' => $object,
    ])->assertStatus(202);

    expect($this->user->remoteFollowers()->count())->toBe(1);
})->with([
    'a like' => [['type' => 'Like', 'object' => 'https://example.com/note']],
    'a follow of someone else' => [['type' => 'Follow', 'object' => 'https://example.com/users/bob']],
]);

it('ignores a follow of someone else', function (): void {
    deliverToInbox($this->user, $this->remote, $this->remote->follow('https://example.com/users/bob'))->assertStatus(202);

    expect(RemoteFollower::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('drops other activities without fetching the sender', function (mixed $activity): void {
    $this->call('POST', route('actor-inbox.store', $this->user), content: is_string($activity) ? $activity : json_encode($activity))
        ->assertStatus(202);

    Http::assertNothingSent();
})->with([
    'a deleted account' => [['type' => 'Delete', 'actor' => 'https://mastodon.example/users/gone', 'object' => 'https://mastodon.example/users/gone']],
    'a like' => [['type' => 'Like', 'actor' => 'https://mastodon.example/users/alice']],
    'not json' => ['not json'],
]);

it('refuses a follow that is not signed by the account that sends it', function (Closure $tamper): void {
    $follow = $this->remote->follow(route('actor.show', $this->user));

    $tamper($this, $follow)->assertUnauthorized();

    expect(RemoteFollower::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'no signature' => fn ($test, array $follow) => $test->call('POST', route('actor-inbox.store', $test->user), content: json_encode($follow)),
    'signed by another key' => fn ($test, array $follow): TestResponse => deliverToInbox($test->user, RemoteActor::generate(), $follow),
    'changed after signing' => fn ($test, array $follow): TestResponse => deliverToInbox($test->user, $test->remote, $follow, ['Digest' => 'SHA-256='.base64_encode(hash('sha256', 'something else', true))]),
    'signed long ago' => fn ($test, array $follow): TestResponse => deliverToInbox($test->user, $test->remote, $follow, $test->remote->sign(
        parse_url(route('actor-inbox.store', $test->user), PHP_URL_PATH),
        request()->getHttpHost(),
        json_encode($follow, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        date: gmdate('D, d M Y H:i:s \G\M\T', now()->subDay()->getTimestamp()),
    )),
    'without signing the digest' => fn ($test, array $follow): TestResponse => deliverToInbox($test->user, $test->remote, $follow, $test->remote->sign(
        parse_url(route('actor-inbox.store', $test->user), PHP_URL_PATH),
        request()->getHttpHost(),
        json_encode($follow, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        headers: '(request-target) host date',
    )),
    'with an unknown algorithm' => fn ($test, array $follow): TestResponse => deliverToInbox($test->user, $test->remote, $follow, $test->remote->sign(
        parse_url(route('actor-inbox.store', $test->user), PHP_URL_PATH),
        request()->getHttpHost(),
        json_encode($follow, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        algorithm: 'rsa-sha1',
    )),
    'for another actor' => fn ($test, array $follow): TestResponse => deliverToInbox($test->user, $test->remote, [...$follow, 'actor' => 'https://mastodon.example/users/bob']),
]);

it('refuses a follow when the sender key cannot be trusted', function (array $document, ?string $id = null): void {
    $remote = RemoteActor::generate($id ?? 'https://mastodon.example/users/carol');
    Http::fake([$remote->id => Http::response([...$remote->document(), ...$document])]);

    deliverToInbox($this->user, $remote, $remote->follow(route('actor.show', $this->user)))->assertUnauthorized();

    expect(RemoteFollower::query()->count())->toBe(0);
})->with([
    'a key owned by someone else' => [['publicKey' => ['id' => 'https://mastodon.example/users/carol#main-key', 'owner' => 'https://mastodon.example/users/bob', 'publicKeyPem' => 'x']]],
    'a key with another id' => [['publicKey' => ['id' => 'https://mastodon.example/users/carol#other-key', 'owner' => 'https://mastodon.example/users/carol', 'publicKeyPem' => 'x']]],
    'no key' => [['publicKey' => null]],
    'a document for another actor' => [['id' => 'https://mastodon.example/users/bob']],
    'an actor over plain http' => [[], 'http://mastodon.example/users/carol'],
    'an actor on a private address' => [[], 'https://internal.example/users/carol'],
]);

it('refuses a follow when the sender cannot be fetched', function (): void {
    $remote = RemoteActor::generate('https://mastodon.example/users/missing');
    Http::fake([$remote->id => Http::response(status: 404)]);

    deliverToInbox($this->user, $remote, $remote->follow(route('actor.show', $this->user)))->assertUnauthorized();
});

it('refuses a follow when the sender cannot be reached', function (): void {
    $remote = RemoteActor::generate('https://mastodon.example/users/unreachable');
    Http::fake([$remote->id => Http::failedConnection()]);

    deliverToInbox($this->user, $remote, $remote->follow(route('actor.show', $this->user)))->assertUnauthorized();
});

it('does not store a follower without a usable inbox', function (array $document): void {
    $remote = RemoteActor::generate('https://mastodon.example/users/no-inbox');
    Http::fake([$remote->id => Http::response([...$remote->document(), ...$document])]);

    deliverToInbox($this->user, $remote, $remote->follow(route('actor.show', $this->user)))->assertStatus(202);

    expect(RemoteFollower::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'no inbox' => [['inbox' => null]],
    'an inbox over plain http' => [['inbox' => 'http://mastodon.example/users/alice/inbox']],
]);

it('stores a follower without a shared inbox when it is missing or unsafe', function (mixed $endpoints): void {
    $remote = RemoteActor::generate('https://mastodon.example/users/no-shared-inbox');
    Http::fake([$remote->id => Http::response([...$remote->document(), 'endpoints' => $endpoints])]);

    deliverToInbox($this->user, $remote, $remote->follow(route('actor.show', $this->user)))->assertStatus(202);

    expect(RemoteFollower::query()->sole()->shared_inbox_url)->toBeNull();
})->with([
    'no endpoints' => [null],
    'an unsafe shared inbox' => [['sharedInbox' => 'http://mastodon.example/inbox']],
]);

it('lets a local Mastodon follow when private addresses are allowed for testing', function (): void {
    config(['federation.allow_private_addresses' => true]);
    $remote = RemoteActor::generate('http://internal.example:3000/users/dave');
    Http::fake([$remote->id => Http::response($remote->document())]);

    deliverToInbox($this->user, $remote, $remote->follow(route('actor.show', $this->user)))->assertStatus(202);

    expect(RemoteFollower::query()->sole()->actor_url)->toBe($remote->id);
});

it('has no inbox for users who do not federate', function (): void {
    $user = User::factory()->create();

    deliverToInbox($user, $this->remote, $this->remote->follow(route('actor.show', $user)))->assertNotFound();
});
