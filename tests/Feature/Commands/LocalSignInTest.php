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
})->with(['hessel.carole', '@hessel.carole']);

it('searches for the developer when none is given', function (): void {
    $user = User::factory()->create(['username' => 'hessel.carole']);
    User::factory()->create(['username' => 'robin48']);

    $this->artisan('local:sign-in')
        ->expectsSearch('Which developer do you want to sign in as?', 'hessel.carole', 'hess', ['hessel.carole'])
        ->expectsOutputToContain(route('local-session.store', $user))
        ->assertSuccessful();
});

it('fails for an unknown developer', function (): void {
    $this->artisan('local:sign-in', ['username' => 'nobody'])
        ->expectsOutputToContain('There is no developer @nobody.')
        ->assertFailed();
});

it('fails when AUTO_LOGIN is disabled', function (): void {
    config()->set('app.auto_login', false);

    $this->artisan('local:sign-in', ['username' => 'local_dev'])
        ->expectsOutputToContain('Signing in as any developer needs AUTO_LOGIN=true in the local environment.')
        ->assertFailed();
});
