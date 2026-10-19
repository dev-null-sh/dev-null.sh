<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateSignedRequest;
use App\Http\Middleware\AuthenticateSshSignature;
use App\Models\Commit;
use App\Models\Passkey;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Services\Passkeys;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SoftwareAuthenticator;

function passkeyFor(User $user, SoftwareAuthenticator $authenticator, string $name = '1Password'): Passkey
{
    $credential = $authenticator->register(resolve(Passkeys::class)->creationOptions($user));

    return $user->passkeys()->create([
        'name' => $name,
        'credential_id' => $credential['credential_id'],
        'credential' => resolve(Passkeys::class)->checkRegistration($user, $credential),
    ]);
}

function signingUser(?SoftwareAuthenticator $authenticator = null): User
{
    $user = User::factory()->create(['requires_signed_commits' => true]);
    passkeyFor($user, $authenticator ?? SoftwareAuthenticator::generate());

    return $user;
}

/**
 * @param  array<string, mixed>  $draft
 * @param  array<string, mixed>  $overrides
 * @return array<string, string>
 */
function signDraft(User $user, SoftwareAuthenticator $authenticator, array $draft, array $overrides = []): array
{
    $response = test()->actingAs($user)->postJson(route('signing-challenge.store'), $draft)->assertOk();

    return $authenticator->sign($response->json('nonce'), $response->json('options'), $overrides);
}

/**
 * @return array<string, string>
 */
function confirmSigning(User $user, SoftwareAuthenticator $authenticator, string $action): array
{
    $response = test()->actingAs($user)->postJson(route('signing-challenge.store'), ['action' => $action])->assertOk();

    return $authenticator->sign($response->json('nonce'), $response->json('options'));
}

function fakeUpstreamIssues(): void
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    assert($key !== false);
    openssl_pkey_export($key, $privateKey);
    config(['services.github_app' => ['id' => '12345', 'private_key' => $privateKey]]);

    Http::fake([
        'api.github.com/repos/nunomaduro/dev-null.sh/installation' => Http::response(['id' => 99]),
        'api.github.com/app/installations/99/access_tokens' => Http::response(['token' => 'installation-token'], 201),
        'api.github.com/repos/nunomaduro/dev-null.sh/issues' => Http::response(['number' => 123, 'html_url' => 'https://github.com/nunomaduro/dev-null.sh/issues/123'], 201),
    ]);
}

it('refuses an unsigned commit from a user who requires signing', function (): void {
    $user = signingUser();

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Who wrote this?'])
        ->assertSessionHasErrors(['signature' => 'Your account requires signed commits. Sign this commit with your passkey.']);

    expect($user->commits()->count())->toBe(0);
});

it('posts a signed commit and keeps a proof that anyone who can see the commit can check', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = User::factory()->create();
    passkeyFor($user, $authenticator);

    $signature = signDraft($user, $authenticator, ['body' => 'Signed :rocket:']);
    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Signed :rocket:', 'signature' => $signature])->assertSessionHasNoErrors();

    $commit = $user->commits()->sole();

    expect($commit->body)->toBe('Signed 🚀')
        ->and($commit->signed_at)->not->toBeNull()
        ->and($commit->signature['statement'])->toContain('Signed :rocket:')
        ->and($user->passkeys()->sole()->last_used_at)->not->toBeNull();

    $response = $this->actingAs($user)->getJson(route('commit-signature.show', $commit))
        ->assertOk()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('passkey.name', '1Password')
        ->assertJsonPath('passkey.algorithm', 'ES256');

    expect($response->json('client_data.challenge'))->toBe($response->json('challenge'));

    $this->actingAs(User::factory()->create())->getJson(route('commit-signature.show', $commit))->assertNotFound();
});

it('treats CRLF line endings, as multipart forms send them, like LF', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);

    $signature = signDraft($user, $authenticator, ['body' => "First line.\nSecond line."]);

    $this->actingAs($user)->post(route('commit.store'), ['body' => "First line.\r\nSecond line.", 'signature' => $signature])->assertSessionHasNoErrors();

    expect($user->commits()->count())->toBe(1);
});

it('refuses a commit that differs from what was signed', function (string $change): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);

    $signature = signDraft($user, $authenticator, ['body' => 'What I meant to say.']);

    $posted = match ($change) {
        'body' => ['body' => 'What an attacker says.'],
        'quote' => ['body' => 'What I meant to say.', 'quoted_commit_id' => Commit::factory()->create()->id],
        'poll' => ['body' => 'What I meant to say.', 'poll' => ['options' => ['Yes', 'No'], 'duration' => 1440]],
    };

    $this->actingAs($user)->post(route('commit.store'), [...$posted, 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'The passkey signed something else than /dev/null asked for.']);

    expect($user->commits()->count())->toBe(0);
})->with(['body', 'quote', 'poll']);

it('refuses a signature made by another key under the passkey id', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);
    $impostor = SoftwareAuthenticator::generate();
    $impostor->credentialId = $authenticator->credentialId;

    $signature = signDraft($user, $impostor, ['body' => 'Forged.']);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Forged.', 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'The passkey signature is not valid.']);
});

it('refuses a signature that is used twice, or whose nonce another request claimed first', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);

    $signature = signDraft($user, $authenticator, ['body' => 'Only once.']);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Only once.', 'signature' => $signature])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Only once.', 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'The signature expired or was already used. Try again.']);

    $signature = signDraft($user, $authenticator, ['body' => 'Only once.']);
    Cache::add("passkey-challenge-spent:{$signature['nonce']}", true, 300);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Only once.', 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'The signature expired or was already used. Try again.']);

    expect($user->commits()->count())->toBe(1);
});

it('refuses a signature made for another site or without verifying the user', function (array $overrides, string $message): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);

    $signature = signDraft($user, $authenticator, ['body' => 'Phished?'], $overrides);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Phished?', 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => $message]);
})->with([
    'another origin' => [['origin' => 'https://dev-null.example'], 'The passkey signed something else than /dev/null asked for.'],
    'another relying party' => [['rp_id' => 'dev-null.example'], 'The passkey belongs to another site.'],
    'user not verified' => [['flags' => 0x01], 'The passkey did not verify you.'],
    'a registration' => [['type' => 'webauthn.create'], 'The passkey signed something else than /dev/null asked for.'],
]);

it('refuses a signature from a passkey of another user', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser();
    passkeyFor(User::factory()->create(), $authenticator);

    $signature = signDraft($user, $authenticator, ['body' => 'Not my key.']);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Not my key.', 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'That passkey is not one of yours.']);
});

it('refuses a signature with a nonce issued to another user', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);
    $other = signingUser();

    $signature = signDraft($other, $authenticator, ['body' => 'Borrowed nonce.']);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Borrowed nonce.', 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'The signature expired or was already used. Try again.']);
});

it('refuses a signature after five minutes', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);

    $signature = signDraft($user, $authenticator, ['body' => 'Too late.']);
    $this->travel(301)->seconds();

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Too late.', 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'The signature expired or was already used. Try again.']);
});

it('signs the images of a commit', function (): void {
    Queue::fake();
    Storage::fake();

    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);
    $image = UploadedFile::fake()->image('shipped.png', 10, 10);
    $other = UploadedFile::fake()->image('other.png', 12, 12);

    $signature = signDraft($user, $authenticator, ['body' => 'Look.', 'image_hashes' => [hash_file('sha256', $image->getRealPath())]]);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Look.', 'images' => [$other], 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'The passkey signed something else than /dev/null asked for.']);

    $signature = signDraft($user, $authenticator, ['body' => 'Look.', 'image_hashes' => [hash_file('sha256', $image->getRealPath())]]);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Look.', 'images' => [$image], 'signature' => $signature])
        ->assertSessionHasNoErrors();

    expect($user->commits()->sole()->images)->toHaveCount(1);
});

it('signs replies to the commit they answer', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);
    [$parent, $otherParent] = Commit::factory()->count(2)->create();

    $signature = signDraft($user, $authenticator, ['body' => 'Agreed.', 'parent_id' => $parent->id]);

    $this->actingAs($user)->postJson(route('commit-reply.store', $otherParent), ['body' => 'Agreed.', 'signature' => $signature])
        ->assertJsonValidationErrors(['signature' => 'The passkey signed something else than /dev/null asked for.']);

    $signature = signDraft($user, $authenticator, ['body' => 'Agreed.', 'parent_id' => $parent->id]);

    $this->actingAs($user)->postJson(route('commit-reply.store', $parent), ['body' => 'Agreed.', 'signature' => $signature])->assertCreated();

    expect($user->commits()->sole()->signature['statement'])->toContain($parent->id);
});

it('signs scheduled commits with the time they are scheduled for, which only the scheduling endpoint takes', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);
    $scheduledAt = now()->addDay()->startOfMinute()->toIso8601String();
    $sign = fn (): array => signDraft($user, $authenticator, ['body' => 'Later.', 'scheduled_at' => $scheduledAt]);

    $this->actingAs($user)->post(route('scheduled-commit.store'), ['body' => 'Later.', 'scheduled_at' => now()->addDays(2)->startOfMinute()->toIso8601String(), 'signature' => $sign()])
        ->assertSessionHasErrors(['signature' => 'The passkey signed something else than /dev/null asked for.']);
    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Later.', 'scheduled_at' => $scheduledAt, 'signature' => $sign()])
        ->assertSessionHasErrors(['signature' => 'The passkey signed something else than /dev/null asked for.']);
    $this->actingAs($user)->post(route('scheduled-commit.store'), ['body' => 'Later.', 'scheduled_at' => $scheduledAt, 'signature' => $sign()])
        ->assertSessionHasNoErrors();

    expect($user->commits()->count())->toBe(0);
});

it('refuses commits from the terminal and the app while signing is required', function (string $route, string $middleware): void {
    $user = signingUser();

    $this->withoutMiddleware($middleware)->actingAs($user)->postJson(route($route), ['body' => 'From elsewhere.'])
        ->assertUnprocessable()
        ->assertSee('Your account requires commits signed with a passkey, so it only takes commits from the web.');

    expect($user->commits()->count())->toBe(0);
})->with([
    'terminal' => ['cli-commit.store', AuthenticateSshSignature::class],
    'app' => ['api-commit.store', AuthenticateSignedRequest::class],
]);

it('turns signing on and off only when a passkey confirms it', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('signing.update'), ['enabled' => true])->assertSessionHasErrors(['enabled' => 'Add a passkey first.']);

    passkeyFor($user, $authenticator);

    $this->actingAs($user)->put(route('signing.update'), ['enabled' => true])->assertSessionHasErrors(['signature' => 'Confirm this change with your passkey.']);
    $this->actingAs($user)->put(route('signing.update'), ['enabled' => true, 'signature' => confirmSigning($user, $authenticator, 'disable')])
        ->assertSessionHasErrors(['signature' => 'The passkey signed something else than /dev/null asked for.']);
    $this->actingAs($user)->put(route('signing.update'), ['enabled' => true, 'signature' => confirmSigning($user, $authenticator, 'enable')])->assertSessionHasNoErrors();

    expect($user->refresh()->requires_signed_commits)->toBeTrue();

    $this->actingAs($user)->put(route('signing.update'), ['enabled' => false])->assertSessionHasErrors(['signature']);
    $this->actingAs($user)->put(route('signing.update'), ['enabled' => false, 'signature' => confirmSigning($user, $authenticator, 'disable')])->assertSessionHasNoErrors();

    expect($user->refresh()->requires_signed_commits)->toBeFalse();
});

it('shows the passkeys and the setting on the signing page', function (): void {
    $user = signingUser();

    $this->actingAs($user)->get(route('signing.edit'))
        ->assertInertia(fn ($page) => $page
            ->component('signing/edit')
            ->where('requiresSignedCommits', true)
            ->has('passkeys', 1)
            ->where('passkeys.0.name', '1Password')
            ->missing('passkeys.0.credential'));
});

it('refuses a passkey that is incomplete or whose id is too long to store', function (array $credential, string $error): void {
    $user = User::factory()->create();
    $registered = SoftwareAuthenticator::generate()->register(resolve(Passkeys::class)->creationOptions($user));

    $this->actingAs($user)->post(route('passkey.store'), ['name' => 'Laptop', 'credential' => array_filter([...$registered, ...$credential], fn (mixed $value): bool => $value !== null)])
        ->assertSessionHasErrors([$error]);

    expect($user->passkeys()->count())->toBe(0);
})->with([
    'incomplete' => [['public_key' => null], 'credential.public_key'],
    'id too long' => [['credential_id' => str_repeat('a', 256)], 'credential.credential_id'],
]);

it('confirms a new passkey with an existing one while signing is required', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);

    $newKey = SoftwareAuthenticator::generate();
    $options = $this->actingAs($user)->postJson(route('passkey.options'))->assertOk()->json();

    $this->actingAs($user)->post(route('passkey.store'), ['name' => 'YubiKey', 'credential' => $newKey->register($options)])
        ->assertSessionHasErrors(['signature' => 'Confirm this change with your passkey.']);

    $options = $this->actingAs($user)->postJson(route('passkey.options'))->assertOk()->json();

    $this->actingAs($user)->post(route('passkey.store'), ['name' => 'YubiKey', 'credential' => $newKey->register($options), 'signature' => confirmSigning($user, $authenticator, 'add')])
        ->assertSessionHasNoErrors();

    expect($user->passkeys()->pluck('name')->sort()->values()->all())->toBe(['1Password', 'YubiKey']);
});

it('adds a passkey without confirmation while signing is not required', function (): void {
    $user = User::factory()->create();

    $options = $this->actingAs($user)->postJson(route('passkey.options'))->assertOk()->json();

    $this->actingAs($user)->post(route('passkey.store'), ['name' => '1Password', 'credential' => SoftwareAuthenticator::generate()->register($options)])
        ->assertSessionHasNoErrors();

    expect($user->passkeys()->sole()->name)->toBe('1Password');
});

it('refuses a passkey registered for another site', function (): void {
    $user = User::factory()->create();

    $options = $this->actingAs($user)->postJson(route('passkey.options'))->assertOk()->json();

    $this->actingAs($user)->post(route('passkey.store'), ['name' => 'Phished', 'credential' => SoftwareAuthenticator::generate()->register($options, ['origin' => 'https://dev-null.example'])])
        ->assertSessionHasErrors(['credential' => 'The passkey signed something else than /dev/null asked for.']);

    expect($user->passkeys()->count())->toBe(0);
});

it('keeps the last passkey while signing is required', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);
    $passkey = $user->passkeys()->sole();

    $this->actingAs($user)->delete(route('passkey.destroy', $passkey), ['signature' => confirmSigning($user, $authenticator, "remove:{$passkey->id}")])
        ->assertSessionHasErrors(['signature' => 'Turn off signed commits before you remove your last passkey.']);

    $second = SoftwareAuthenticator::generate();
    passkeyFor($user, $second, 'YubiKey');

    $this->actingAs($user)->delete(route('passkey.destroy', $passkey))->assertSessionHasErrors(['signature']);
    $this->actingAs($user)->delete(route('passkey.destroy', $passkey), ['signature' => confirmSigning($user, $second, "remove:{$passkey->id}")])
        ->assertSessionHasNoErrors();

    expect($user->passkeys()->sole()->name)->toBe('YubiKey');
});

it('does not remove the passkeys of another user', function (): void {
    $passkey = passkeyFor(User::factory()->create(), SoftwareAuthenticator::generate());

    $this->actingAs(User::factory()->create())->delete(route('passkey.destroy', $passkey))->assertNotFound();

    expect($passkey->exists)->toBeTrue();
});

it('rejects a passkey whose counter went back', function (): void {
    $authenticator = SoftwareAuthenticator::generate(counter: 5);
    $user = signingUser($authenticator);
    $passkey = $user->passkeys()->sole();
    $passkey->update(['credential' => [...$passkey->credential, 'sign_count' => 100]]);

    $signature = signDraft($user, $authenticator, ['body' => 'Cloned key?']);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Cloned key?', 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'The passkey counter went back. It may have been copied.']);
});

it('reports a proof whose statement was changed as not verified', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);

    $signature = signDraft($user, $authenticator, ['body' => 'The original.']);
    $this->actingAs($user)->post(route('commit.store'), ['body' => 'The original.', 'signature' => $signature])->assertSessionHasNoErrors();

    $commit = $user->commits()->sole();
    $commit->update(['signature' => [...$commit->signature, 'statement' => str_replace('The original.', 'Rewritten.', $commit->signature['statement'])]]);

    $this->actingAs($user)->getJson(route('commit-signature.show', $commit))->assertOk()->assertJsonPath('verified', false);
});

it('has no proof for an unsigned commit', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Not signed.'])->assertSessionHasNoErrors();

    $commit = $user->commits()->sole();

    expect($commit->signed_at)->toBeNull();

    $this->actingAs($user)->getJson(route('commit-signature.show', $commit))->assertNotFound();
});

it('carries the proof of a scheduled commit to the commit it becomes, even after its passkey is removed', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = User::factory()->create();
    $passkey = passkeyFor($user, $authenticator);
    $scheduledAt = now()->addHour()->startOfMinute()->toIso8601String();

    $signature = signDraft($user, $authenticator, ['body' => 'Signed for later.', 'scheduled_at' => $scheduledAt]);
    $this->actingAs($user)->post(route('scheduled-commit.store'), ['body' => 'Signed for later.', 'scheduled_at' => $scheduledAt, 'signature' => $signature])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->delete(route('passkey.destroy', $passkey))->assertSessionHasNoErrors();

    $this->travel(2)->hours();
    $this->artisan('commits:submit-scheduled')->assertSuccessful();

    $commit = $user->commits()->sole();

    expect($commit->signature['passkey_name'])->toBe('1Password')
        ->and(ScheduledCommit::query()->sole()->signature)->toBeNull();

    $this->actingAs($user)->getJson(route('commit-signature.show', $commit))->assertJsonPath('verified', true);
});

it('opens an issue only when a passkey signed its command and body, while signing is required', function (): void {
    fakeUpstreamIssues();
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Search loses its filters.'])
        ->assertJsonValidationErrors(['signature' => 'Your account requires signed commits. Sign this issue with your passkey.']);

    $signature = signDraft($user, $authenticator, ['command' => '/bug', 'body' => 'Search loses its filters.']);

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/feature', 'body' => 'Search loses its filters.', 'signature' => $signature])
        ->assertJsonValidationErrors(['signature' => 'The passkey signed something else than /dev/null asked for.']);

    $signature = signDraft($user, $authenticator, ['command' => '/bug', 'body' => 'Search loses its filters.']);

    $this->actingAs($user)->postJson(route('issue.store'), ['command' => '/bug', 'body' => 'Search loses its filters.', 'signature' => $signature])
        ->assertCreated();

    Http::assertSentCount(3);
});

it('does not take the signature of an issue for a commit with the same body', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);

    $signature = signDraft($user, $authenticator, ['command' => '/feature', 'body' => 'Dark mode, please.']);

    $this->actingAs($user)->post(route('commit.store'), ['body' => 'Dark mode, please.', 'signature' => $signature])
        ->assertSessionHasErrors(['signature' => 'The passkey signed something else than /dev/null asked for.']);
});

it('does not turn signing on while an unsigned scheduled commit is waiting', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = User::factory()->create();
    passkeyFor($user, $authenticator);
    $scheduled = ScheduledCommit::factory()->for($user)->create();
    ScheduledCommit::factory()->for($user)->create(['submitted_at' => now()->subDay()]);
    ScheduledCommit::factory()->for($user)->create(['canceled_at' => now()->subDay()]);

    $this->actingAs($user)->put(route('signing.update'), ['enabled' => true, 'signature' => confirmSigning($user, $authenticator, 'enable')])
        ->assertSessionHasErrors(['enabled' => 'Cancel your unsigned scheduled commits first, or post them signed.']);

    expect($user->refresh()->requires_signed_commits)->toBeFalse();

    $scheduled->update(['signature' => ['nonce' => 'signed']]);

    $this->actingAs($user)->put(route('signing.update'), ['enabled' => true, 'signature' => confirmSigning($user, $authenticator, 'enable')])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->requires_signed_commits)->toBeTrue();
});

it('refuses a signature without all of its parts', function (string $route, string $method, array $data): void {
    $user = signingUser();
    $route = $route === 'passkey.destroy' ? route($route, $user->passkeys()->sole()) : route($route);

    $this->actingAs($user)->json($method, $route, [...$data, 'signature' => ['nonce' => ['0123456789abcdef0123456789abcdef']]])
        ->assertJsonValidationErrors(['signature', 'signature.nonce']);
})->with([
    'commit' => ['commit.store', 'POST', ['body' => 'Half a signature.']],
    'setting' => ['signing.update', 'PUT', ['enabled' => false]],
    'passkey' => ['passkey.destroy', 'DELETE', []],
]);

it('forgets what a canceled scheduled commit signed', function (): void {
    $authenticator = SoftwareAuthenticator::generate();
    $user = signingUser($authenticator);
    $scheduledAt = now()->addDay()->startOfMinute()->toIso8601String();

    $signature = signDraft($user, $authenticator, ['body' => 'Changed my mind.', 'scheduled_at' => $scheduledAt]);
    $this->actingAs($user)->post(route('scheduled-commit.store'), ['body' => 'Changed my mind.', 'scheduled_at' => $scheduledAt, 'signature' => $signature])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)->delete(route('scheduled-commit.destroy', ScheduledCommit::query()->sole()));

    expect(ScheduledCommit::query()->sole())
        ->canceled_at->not->toBeNull()
        ->signature->toBeNull();
});
