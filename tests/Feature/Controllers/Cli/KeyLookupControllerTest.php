<?php

declare(strict_types=1);

use App\Contracts\Services\GitHubProvider;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Support\SshKeyPair;

it('picks the key that is on the github account of a user', function (): void {
    $unknown = SshKeyPair::generate()->publicKey;
    $known = SshKeyPair::generate()->publicKey;
    SshKey::factory()->withPublicKey($known)->create();

    Http::fake();

    $response = $this->call('POST', route('cli-key-lookup.store'), content: implode("\n", [
        'not a key',
        'ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBA==',
        $unknown->toOpenSsh().' laptop',
        $known->toOpenSsh().' work',
    ]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertContent($known->toOpenSsh()."\n");

    // Anyone can call this endpoint: it must not spend the GitHub API quota.
    Http::assertNothingSent();
});

it('fails when none of the keys are on the github account of a user', function (): void {
    $response = $this->call('POST', route('cli-key-lookup.store'), content: SshKeyPair::generate()->publicKey->toOpenSsh());

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('None of your SSH keys are on the GitHub account of a /dev/null user.')
        ->assertSee('then click Sync from GitHub on '.route('user-profile.edit').'.');
});

it('finds a key added on github after signing in once the user syncs from github', function (): void {
    $user = User::factory()->create(['github_id' => '42']);
    $key = SshKeyPair::generate()->publicKey;
    $github = $this->mock(GitHubProvider::class);
    $github->shouldReceive('account')->with('42')->andReturn(['login' => 'taylor']);
    $github->shouldReceive('login')->with('42')->andReturn('taylor');
    $github->shouldReceive('sshKeys')->with('taylor')->andReturn([$key->toOpenSsh()]);

    $this->call('POST', route('cli-key-lookup.store'), content: $key->toOpenSsh())->assertNotFound();

    $this->actingAs($user)->put(route('user-profile.update'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Synced from GitHub.']);

    $this->call('POST', route('cli-key-lookup.store'), content: $key->toOpenSsh())
        ->assertOk()
        ->assertContent($key->toOpenSsh()."\n");
});

it('only considers the first 20 keys', function (): void {
    $known = SshKeyPair::generate()->publicKey;
    SshKey::factory()->withPublicKey($known)->create();

    $unknownKeys = str_repeat(SshKeyPair::generate()->publicKey->toOpenSsh()."\n", 20);

    $response = $this->call('POST', route('cli-key-lookup.store'), content: $unknownKeys.$known->toOpenSsh());

    $response->assertNotFound();
});

it('rate limits key lookups', function (): void {
    $publicKey = SshKeyPair::generate()->publicKey->toOpenSsh();

    for ($i = 0; $i < 60; $i++) {
        $this->call('POST', route('cli-key-lookup.store'), content: $publicKey)->assertNotFound();
    }

    $response = $this->call('POST', route('cli-key-lookup.store'), content: $publicKey);

    $response->assertTooManyRequests()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertHeader('Retry-After', '60')
        ->assertContent("Too Many Attempts.\n");
});
