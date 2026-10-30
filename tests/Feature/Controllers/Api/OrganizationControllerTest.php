<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

it('shows an organization profile', function (): void {
    Organization::factory()->create([
        'handle' => 'laravel',
        'name' => 'Laravel',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/958072',
        'bio' => 'The PHP framework for web artisans.',
        'location' => 'United States of America',
        'website' => 'https://laravel.com',
    ]);

    $this->getJson(route('api-organization.show', ['handle' => 'laravel']))
        ->assertOk()
        ->assertExactJson(['data' => [
            'handle' => 'laravel',
            'name' => 'Laravel',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/958072',
            'bio' => 'The PHP framework for web artisans.',
            'location' => 'United States of America',
            'website' => 'https://laravel.com',
            'github_url' => 'https://github.com/laravel',
            'html_url' => route('user.show', ['user' => 'laravel']),
        ]]);
});

it('returns not found for handles without an organization page', function (): void {
    User::factory()->create(['handle' => 'octocat']);

    $this->getJson('/api/v1/organizations/octocat')->assertNotFound();
    $this->getJson('/api/v1/organizations/missing')->assertNotFound();
});
