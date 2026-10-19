<?php

declare(strict_types=1);

use App\Models\User;

function webFingerHost(): string
{
    return (string) parse_url(config()->string('app.url'), PHP_URL_HOST);
}

it('finds a user who federates by their handle', function (): void {
    $user = User::factory()->create(['username' => 'areinger', 'federates' => true]);

    $response = $this->getJson(route('webfinger.show', ['resource' => 'acct:areinger@'.webFingerHost()]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/jrd+json')
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertExactJson([
            'subject' => 'acct:areinger@'.webFingerHost(),
            'aliases' => [route('user.show', $user), route('actor.show', $user)],
            'links' => [
                ['rel' => 'self', 'type' => 'application/activity+json', 'href' => route('actor.show', $user)],
                ['rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => route('user.show', $user)],
            ],
        ]);
});

it('ignores the case of the username and the host', function (): void {
    User::factory()->create(['username' => 'areinger', 'federates' => true]);

    $this->getJson(route('webfinger.show', ['resource' => 'acct:AReinger@'.mb_strtoupper(webFingerHost())]))
        ->assertOk()
        ->assertJsonPath('subject', 'acct:areinger@'.webFingerHost());
});

it('does not find users who do not federate, or handles of other servers', function (string $resource): void {
    User::factory()->create(['username' => 'areinger']);
    User::factory()->create(['username' => 'federated', 'federates' => true]);

    $this->getJson(route('webfinger.show', ['resource' => str_replace('{host}', webFingerHost(), $resource)]))->assertNotFound();
})->with([
    'not federating' => 'acct:areinger@{host}',
    'unknown user' => 'acct:nobody@{host}',
    'other server' => 'acct:federated@mastodon.example',
    'not an account' => 'https://{host}/@federated',
    'no host' => 'acct:federated',
]);

it('does not find anything without a resource', function (): void {
    $this->getJson(route('webfinger.show'))->assertNotFound();
});
