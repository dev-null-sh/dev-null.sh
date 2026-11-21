<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\URL;

it('asks before unsubscribing, since mail scanners open every link', function (): void {
    $user = User::factory()->create();

    $this->get(URL::signedRoute('unsubscription.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('unsubscription/show')->where('unsubscribed', false));

    expect($user->refresh()->emails_unsubscribed_at)->toBeNull();
});

it('unsubscribes from the signed link without signing in', function (): void {
    $this->freezeSecond();
    $user = User::factory()->create();
    $url = URL::signedRoute('unsubscription.show', $user);

    $this->post($url)->assertRedirect($url);

    expect($user->refresh()->emails_unsubscribed_at?->toIso8601String())->toBe(now()->toIso8601String());
    $this->get($url)->assertInertia(fn ($page) => $page->where('unsubscribed', true));
});

it('takes the one-click unsubscribe that mail providers post', function (): void {
    $user = User::factory()->create();

    $this->post(URL::signedRoute('unsubscription.store', $user), ['List-Unsubscribe' => 'One-Click'])->assertRedirect();

    expect($user->refresh()->emails_unsubscribed_at)->not->toBeNull();
});

it('rejects links that were not signed', function (): void {
    $user = User::factory()->create();

    $this->get(route('unsubscription.show', $user))->assertNotFound();
    $this->post(route('unsubscription.store', $user))->assertNotFound();

    expect($user->refresh()->emails_unsubscribed_at)->toBeNull();
});
