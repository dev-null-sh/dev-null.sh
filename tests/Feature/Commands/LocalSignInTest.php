<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config()->set('app.auto_login', true);
});

it('prints the link that signs in as a developer', function (string $username): void {
    $user = User::factory()->create(['username' => 'hessel.carole']);

    $this->artisan('local:sign-in', ['username' => $username])
        ->expectsOutputToContain('Open this link to sign in as @hessel.carole:')
        ->expectsOutputToContain(route('local-session.store', $user))
        ->assertSuccessful();
})->with(['hessel.carole', '@hessel.carole', 'hess', '@hess']);

it('prefers the developer with exactly that username', function (): void {
    User::factory()->create(['username' => 'abob']);
    $bob = User::factory()->create(['username' => 'bob']);

    $this->artisan('local:sign-in', ['username' => 'bob'])
        ->expectsOutputToContain(route('local-session.store', $bob))
        ->assertSuccessful();
});

it('picks the first match of what was typed when no developer is given', function (): void {
    $user = User::factory()->create(['username' => 'hessel.carole']);
    User::factory()->create(['username' => 'hessel.zoe']);
    User::factory()->create(['username' => 'robin48']);

    $this->artisan('local:sign-in')
        ->expectsQuestion('Which developer do you want to sign in as?', 'hessel')
        ->expectsOutputToContain(route('local-session.store', $user))
        ->assertSuccessful();
});

it('fails when no developer matches', function (): void {
    $this->artisan('local:sign-in', ['username' => 'nobody'])
        ->expectsOutputToContain('There is no developer matching @nobody.')
        ->assertFailed();
});

it('fails when AUTO_LOGIN is disabled', function (): void {
    config()->set('app.auto_login', false);

    $this->artisan('local:sign-in', ['username' => 'local_dev'])
        ->expectsOutputToContain('Signing in as any developer needs AUTO_LOGIN=true in the local environment.')
        ->assertFailed();
});
