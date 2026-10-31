<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('may follow and unfollow an organization by its handle', function (): void {
    $organization = Organization::factory()->create(['handle' => 'laravel']);

    signedApiRequest($this->keyPair, 'POST', route('api-organization-follow.store', ['handle' => 'laravel']))->assertNoContent();

    expect($this->user->followedOrganizations()->pluck('organizations.id')->all())->toBe([$organization->id]);

    signedApiRequest($this->keyPair, 'DELETE', route('api-organization-follow.destroy', ['handle' => 'laravel']))->assertNoContent();

    expect($this->user->followedOrganizations()->count())->toBe(0);
});

it('returns not found for handles without an organization page', function (string $method, string $route): void {
    User::factory()->create(['handle' => 'octocat']);

    signedApiRequest($this->keyPair, $method, route($route, ['handle' => 'octocat']))->assertNotFound();
    signedApiRequest($this->keyPair, $method, route($route, ['handle' => 'missing']))->assertNotFound();
})->with([
    ['POST', 'api-organization-follow.store'],
    ['DELETE', 'api-organization-follow.destroy'],
]);

it('requires a signed request', function (): void {
    Organization::factory()->create(['handle' => 'laravel']);

    $this->postJson(route('api-organization-follow.store', ['handle' => 'laravel']))->assertUnauthorized();
});
