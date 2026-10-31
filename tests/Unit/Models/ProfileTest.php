<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('finds a profile by its handle whatever its case, keeping the case GitHub gives it', function (): void {
    $user = User::factory()->create(['handle' => 'OctoCat']);

    expect(Profile::query()->where('handle', 'octocat')->sole()->is($user->profile))->toBeTrue()
        ->and(Profile::query()->whereIn('handle', ['OCTOCAT'])->value('handle'))->toBe('OctoCat');
});

it('keeps each handle to one profile whatever its case, like GitHub', function (): void {
    User::factory()->create(['handle' => 'laravel']);

    expect(fn () => Organization::factory()->create(['handle' => 'Laravel']))->toThrow(UniqueConstraintViolationException::class);
});
