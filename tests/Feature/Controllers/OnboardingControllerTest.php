<?php

declare(strict_types=1);

use App\Enums\Log;
use App\Models\Commit;
use App\Models\User;

it('renders the onboarding for a new user', function (): void {
    $user = User::factory()->notOnboarded()->create([
        'id' => '3a788e93-4cf7-4819-9377-94be2f329563',
        'preferred_lane' => 1,
    ]);

    $response = $this->actingAs($user)
        ->get(route('onboarding.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/show')
            ->where('preferredLane', 1)
            ->where('automaticLane', 4)
            ->where('commitScriptUrl', route('cli-script.show'))
            ->where('suggestions', []));
});

it('suggests developers to follow', function (): void {
    $user = User::factory()->notOnboarded()->create();
    $authors = User::factory()->count(6)->has(Commit::factory())->create();

    $response = $this->actingAs($user)
        ->get(route('onboarding.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('suggestions', 5)
            ->where('suggestions', fn ($suggestions): bool => collect($suggestions)
                ->pluck('id')
                ->diff($authors->modelKeys())
                ->isEmpty()));
});

it('is not found once the user has onboarded', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('onboarding.show'));

    $response->assertNotFound();
});

it('completes the onboarding and lands on the log', function (): void {
    $user = User::factory()->notOnboarded()->create();

    $response = $this->actingAs($user)
        ->put(route('onboarding.update'));

    $response->assertRedirectToRoute('log.show', Log::Following);

    expect($user->refresh()->isOnboarded())->toBeTrue();

    $this->get(route('onboarding.show'))->assertNotFound();
});

it('lands on the page the user was headed to before signing in', function (): void {
    $user = User::factory()->notOnboarded()->create();
    $author = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['url.intended' => route('user.show', $author)])
        ->put(route('onboarding.update'));

    $response->assertRedirectToRoute('user.show', $author);
});

it('requires authentication', function (): void {
    $this->get(route('onboarding.show'))->assertRedirectToRoute('home');
    $this->put(route('onboarding.update'))->assertRedirectToRoute('home');
});
