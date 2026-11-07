<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Date;

it('asks the user to accept the terms in force on every page until they accept them', function (): void {
    config(['legal.terms_version' => '2026-11-01T00:00:00Z']);
    Date::setTestNow('2026-11-01 12:00:00');

    $user = User::factory()->create(['terms_accepted_at' => '2026-10-31 23:59:59']);

    $this->actingAs($user)
        ->get(route('starred.index'))
        ->assertInertia(fn ($page) => $page->where('auth.termsToAccept', config('legal.terms_version')));

    $this->fromRoute('starred.index')
        ->put(route('user-terms.update'), ['version' => config('legal.terms_version')])
        ->assertRedirectToRoute('starred.index');

    expect($user->refresh()->terms_accepted_at?->toIso8601ZuluString())->toBe('2026-11-01T12:00:00Z');

    $this->get(route('starred.index'))->assertInertia(fn ($page) => $page->where('auth.termsToAccept', null));
});

it('asks again when the terms change after the user accepted them', function (): void {
    config(['legal.terms_version' => '2026-11-01T00:00:00Z']);

    $user = User::factory()->create(['terms_accepted_at' => '2026-11-02 09:00:00']);

    $this->actingAs($user)->get(route('starred.index'))->assertInertia(fn ($page) => $page->where('auth.termsToAccept', null));

    config(['legal.terms_version' => '2026-12-01T00:00:00Z']);

    $this->get(route('starred.index'))->assertInertia(fn ($page) => $page->where('auth.termsToAccept', '2026-12-01T00:00:00Z'));
});

it('asks guests for nothing', function (): void {
    $this->get(route('terms.show'))->assertInertia(fn ($page) => $page->where('auth.termsToAccept', null));
});

it('only accepts the version in force, so a page open since before a change cannot accept terms it never showed', function (mixed $version): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson(route('user-terms.update'), $version === null ? [] : ['version' => $version])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('version');

    expect($user->refresh()->terms_accepted_at)->toBeNull();
})->with([
    'missing' => [null],
    'an old version' => ['2020-01-01T00:00:00Z'],
    'a list' => [['2026-11-01T00:00:00Z']],
    'a number' => [1],
]);

it('requires authentication', function (): void {
    $this->putJson(route('user-terms.update'), ['version' => config('legal.terms_version')])->assertUnauthorized();
});
