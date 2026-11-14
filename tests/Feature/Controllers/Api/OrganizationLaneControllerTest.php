<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * @return array{0: User, 1: Organization}
 */
function apiOrganizationAdmin(array $attributes = []): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['handle' => 'laravel', ...$attributes]);
    $organization->admins()->attach($user->id, ['created_at' => now()]);

    return [$user, $organization];
}

it('lets an admin choose the lane of the organization, and answers with it', function (array $input, ?int $lane, ?string $color): void {
    [$user, $organization] = apiOrganizationAdmin(['preferred_lane' => 2, 'lane_color' => null]);

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-organization-lane.update', 'laravel'), $input)
        ->assertOk()
        ->assertJsonPath('data.handle', 'laravel')
        ->assertJsonPath('data.lane', $color ?? $lane);

    expect($organization->refresh()->preferred_lane)->toBe($lane)
        ->and($organization->lane_color)->toBe($color);
})->with([
    'from the palette' => [['lane' => 5], 5, null],
    'custom' => [['lane' => null, 'color' => '#1a2b3c'], null, '#1a2b3c'],
    'none' => [['lane' => null], null, null],
]);

it('rejects lanes and colors outside what users may choose', function (array $input, string $error): void {
    [$user, $organization] = apiOrganizationAdmin(['preferred_lane' => 2]);

    signedApiRequest(apiKeyPairFor($user), 'PUT', route('api-organization-lane.update', 'laravel'), $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect($organization->refresh()->lane())->toBe(2);
})->with([
    'outside the palette' => [['lane' => 6], 'lane'],
    'short hex' => [['lane' => null, 'color' => '#fff'], 'color'],
    'both' => [['lane' => 1, 'color' => '#1a2b3c'], 'color'],
    'missing lane' => [[], 'lane'],
]);

it('hides the color from everyone but admins of the organization', function (): void {
    [, $organization] = apiOrganizationAdmin(['preferred_lane' => 2]);
    $member = User::factory()->create();
    $organization->members()->attach($member->id, ['role' => 'member', 'created_at' => now()]);

    signedApiRequest(apiKeyPairFor($member), 'PUT', route('api-organization-lane.update', 'laravel'), ['lane' => 1])->assertNotFound();
    signedApiRequest(apiKeyPairFor($member), 'PUT', route('api-organization-lane.update', $member->handle), ['lane' => 1])->assertNotFound();

    expect($organization->refresh()->lane())->toBe(2);
});

it('requires a signed request', function (): void {
    apiOrganizationAdmin();

    $this->putJson(route('api-organization-lane.update', 'laravel'), ['lane' => 1])->assertUnauthorized();
});
