<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\User;

it('shows an organization at the handle no user has, to guests too', function (): void {
    Profile::factory()->organization()->create([
        'github_id' => '958072',
        'handle' => 'laravel',
        'name' => 'Laravel',
        'bio' => 'The PHP framework for web artisans.',
        'website' => 'https://laravel.com',
    ]);

    $this->get('/@laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/show')
            ->where('organization.handle', 'laravel')
            ->where('organization.name', 'Laravel')
            ->where('organization.bio', 'The PHP framework for web artisans.')
            ->where('organization.website', 'https://laravel.com')
            ->missing('organization.github_id')
            ->missing('organization.github_synced_at'));
});

it('does not find handles that are neither a user nor an organization', function (): void {
    $this->get('/@nobody')->assertNotFound();
});

it('shares what every page shares, like the signed-in user', function (): void {
    $viewer = User::factory()->create();
    Profile::factory()->organization()->create(['handle' => 'laravel']);

    $this->actingAs($viewer)->get('/@laravel')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/show')
            ->where('name', config('app.name'))
            ->where('auth.user.id', $viewer->id)
            ->has('auth.unreadMessagesCount'));
});
