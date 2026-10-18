<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\User;

it('signs in locally as any developer', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', true);

    $user = User::factory()->create(['log' => Log::Latest]);

    $this->get(route('local-session.store', $user))
        ->assertRedirectToRoute('log.show', Log::Latest);

    $this->assertAuthenticatedAs($user);
});

it('switches from one developer to another locally', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', true);

    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->get(route('local-session.store', $other))
        ->assertRedirectToRoute('log.show', $other->log);

    $this->assertAuthenticatedAs($other);
});

it('is not found locally when AUTO_LOGIN is disabled', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', false);

    $user = User::factory()->create();

    $this->get(route('local-session.store', $user))->assertNotFound();

    $this->assertGuest();
});

it('is not found outside the local environment', function (): void {
    app()->instance('env', 'production');
    config()->set('app.auto_login', true);

    $user = User::factory()->create();

    $this->get(route('local-session.store', $user))->assertNotFound();

    $this->assertGuest();
});

it('is not found for an unknown developer', function (): void {
    app()->instance('env', 'local');
    config()->set('app.auto_login', true);

    $this->get('/local/@nobody')->assertNotFound();

    $this->assertGuest();
});
