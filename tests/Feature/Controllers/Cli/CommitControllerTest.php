<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\SshKeyPair;

/**
 * @param  array{payload: string, signature: string}  $commit
 */
function postSignedCommit(array $commit): TestResponse
{
    return test()->call('POST', route('cli-commit.store'), server: [
        'CONTENT_TYPE' => 'text/plain; charset=utf-8',
        'HTTP_X_SSH_SIGNATURE' => $commit['signature'],
    ], content: $commit['payload']);
}

beforeEach(function (): void {
    $this->keyPair = SshKeyPair::generate();
    $this->user = User::factory()->create();

    SshKey::factory()->for($this->user)->withPublicKey($this->keyPair->publicKey)->create();

    fakeGitHubSshKeys($this->user, [$this->keyPair->publicKey]);
});

it('creates a commit signed with an ssh key', function (): void {
    $response = postSignedCommit(signedCommit($this->keyPair, 'It was DNS.'));

    $commit = $this->user->commits()->sole();

    $response->assertCreated()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertContent('Committed: '.route('commit.show', [$this->user, $commit])." (pending review, only you can see it until it is approved)\n")
        ->assertCookieMissing(config('session.cookie'));

    expect($commit->body)->toBe('It was DNS.')
        ->and($commit->status)->toBe(CommitStatus::Pending);
});

it('links to the commit when it is published right away', function (): void {
    config(['app.admins' => [$this->user->username]]);

    $response = postSignedCommit(signedCommit($this->keyPair, 'It was DNS.'));

    $commit = $this->user->commits()->sole();

    $response->assertCreated()
        ->assertContent('Committed: '.route('commit.show', [$this->user, $commit])."\n");

    expect($commit->status)->toBe(CommitStatus::Approved);
});

it('replies to a commit', function (): void {
    $parent = Commit::factory()->create(['id' => '01a14036-b0fc-7085-95c9-6e52b0be0130']);

    $response = postSignedCommit(signedCommit($this->keyPair, 'Agreed!', replyTo: '0be0130'));

    $reply = $this->user->commits()->sole();

    $response->assertCreated();

    expect($reply->parent_id)->toBe($parent->id)
        ->and($reply->body)->toBe('Agreed!');
});

it('fails to reply to an unknown commit', function (): void {
    $response = postSignedCommit(signedCommit($this->keyPair, 'Agreed!', replyTo: '0be0130'));

    $response->assertUnprocessable()
        ->assertContent("There is no commit 0be0130 to reply to.\n");

    expect(Commit::query()->count())->toBe(0);
});

it('rejects invalid signatures', function (): void {
    $commit = signedCommit($this->keyPair, 'It was DNS.');

    $response = postSignedCommit([...$commit, 'payload' => $commit['payload'].'!']);

    $response->assertUnauthorized()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertContent("Invalid signature.\n");

    expect(Commit::query()->count())->toBe(0);
});

it('rejects requests without a signature', function (): void {
    $response = $this->call('POST', route('cli-commit.store'), content: 'It was DNS.');

    $response->assertUnauthorized()
        ->assertContent("Malformed SSH signature.\n");
});

it('rejects keys that are no longer on github', function (): void {
    $keyPair = SshKeyPair::generate();

    $response = postSignedCommit(signedCommit($keyPair, 'It was DNS.'));

    $response->assertUnauthorized()
        ->assertContent("This SSH key ({$keyPair->publicKey->fingerprint()}) is not on the GitHub account of a devnull user.\n");
});

it('validates the commit', function (string $body, string $message): void {
    $response = postSignedCommit(signedCommit($this->keyPair, $body));

    $response->assertUnprocessable()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertContent($message."\n");

    expect(Commit::query()->count())->toBe(0);
})->with([
    'too short' => ['  ab  ', 'The body field must be at least 3 characters.'],
    'too long' => [str_repeat('a', 281), 'The body field must not be greater than 280 characters.'],
]);

it('rate limits commits', function (): void {
    for ($i = 0; $i < 10; $i++) {
        postSignedCommit(signedCommit($this->keyPair, "Commit {$i}"))->assertCreated();
    }

    $response = postSignedCommit(signedCommit($this->keyPair, 'One too many'));

    $response->assertTooManyRequests()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertContent("Slow down: 10 commits a minute. Even CI takes breaks.\n");

    expect($this->user->commits()->count())->toBe(10);
});

it('fails when github cannot be reached', function (): void {
    $keyPair = SshKeyPair::generate();
    $user = User::factory()->create();

    SshKey::factory()->for($user)->withPublicKey($keyPair->publicKey)->create();

    Http::fake([
        "api.github.com/user/{$user->github_id}" => Http::response(['message' => 'Server Error'], 500),
    ]);

    $response = postSignedCommit(signedCommit($keyPair, 'It was DNS.'));

    $response->assertServiceUnavailable()
        ->assertContent("Could not reach GitHub to check your SSH key. Try again in a moment.\n");

    expect(Commit::query()->count())->toBe(0);
});

it('answers unknown commit routes in plain text', function (): void {
    $response = $this->get('api/cli/unknown');

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertContent("Not Found\n");
});

it('keeps the default error pages elsewhere', function (): void {
    $response = $this->get('unknown');

    $response->assertNotFound();

    expect($response->headers->get('Content-Type'))->toStartWith('text/html');
});

it('answers unexpected errors in plain text', function (): void {
    Exceptions::fake();

    Route::get('api/cli/boom', fn () => throw new RuntimeException('Boom.'));

    $response = $this->get('api/cli/boom');

    $response->assertInternalServerError()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertContent("Internal Server Error\n");

    Exceptions::assertReported(RuntimeException::class);
});
