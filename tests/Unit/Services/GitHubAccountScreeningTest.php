<?php

declare(strict_types=1);

use App\Services\GitHubAccountScreening;
use Laravel\Socialite\Two\User as GitHubUser;

/**
 * @param  array<string, mixed>  $attributes
 */
function refusal(array $attributes = []): ?string
{
    return resolve(GitHubAccountScreening::class)->refusal(GitHubUser::fake([
        'email' => 'octocat@github.com',
        'created_at' => '2008-01-14T14:00:00Z',
        ...$attributes,
    ]));
}

beforeEach(function (): void {
    $this->freezeSecond();

    config([
        'signup.min_account_age_days' => 7,
        'signup.require_verified_email' => false,
    ]);
});

it('refuses accounts younger than the minimum age', function (): void {
    expect(refusal(['created_at' => now()->subDays(7)->addSecond()->toIso8601String()]))
        ->toContain('younger than 7 days');
});

it('accepts accounts exactly the minimum age', function (): void {
    expect(refusal(['created_at' => now()->subDays(7)->toIso8601String()]))->toBeNull();
});

it('refuses accounts whose age GitHub does not tell', function (): void {
    expect(refusal(['created_at' => null]))->toContain('younger than 7 days');
});

it('skips the age check when the minimum is zero', function (): void {
    config(['signup.min_account_age_days' => 0]);

    expect(refusal(['created_at' => now()->toIso8601String()]))->toBeNull();
});

it('refuses accounts without a verified primary email', function (): void {
    config(['signup.require_verified_email' => true]);

    expect(refusal(['email' => null]))->toContain('verified primary email');
});

it('accepts accounts under every check', function (): void {
    config(['signup.require_verified_email' => true]);

    expect(refusal())->toBeNull();
});
