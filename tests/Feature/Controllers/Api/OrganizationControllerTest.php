<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\User;

it('shows an organization as its page does, to anyone', function (): void {
    Profile::factory()->organization()->create([
        'github_id' => '958072',
        'handle' => 'laravel',
        'name' => 'Laravel',
        'avatar_url' => 'https://avatars.githubusercontent.com/u/958072',
        'bio' => 'The PHP framework for web artisans.',
        'location' => null,
        'website' => 'https://laravel.com',
    ]);

    $this->getJson(route('api-organization.show', 'laravel'))
        ->assertOk()
        ->assertExactJson(['data' => [
            'handle' => 'laravel',
            'name' => 'Laravel',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/958072',
            'bio' => 'The PHP framework for web artisans.',
            'location' => null,
            'website' => 'https://laravel.com',
        ]]);
});

it('finds an organization whatever the case of the handle, as mentions keep the case they were written in', function (): void {
    Profile::factory()->organization()->create(['handle' => 'PHPMailer']);

    $this->getJson(route('api-organization.show', 'phpmailer'))
        ->assertOk()
        ->assertJsonPath('data.handle', 'PHPMailer');
});

it('does not find a handle that is not an organization, even when a user has it', function (): void {
    User::factory()->create(['handle' => 'taylorotwell']);

    $this->getJson(route('api-organization.show', 'taylorotwell'))->assertNotFound();
    $this->getJson(route('api-organization.show', 'nobody'))->assertNotFound();
});
