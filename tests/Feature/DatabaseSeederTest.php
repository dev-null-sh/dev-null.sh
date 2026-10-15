<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('gives the local developer people to follow and followers', function (): void {
    $this->seed(DatabaseSeeder::class);

    $localDeveloper = User::query()->where('github_id', 'local')->sole();

    expect($localDeveloper->username)->toBe('local_dev')
        ->and($localDeveloper->following()->count())->toBe(3)
        ->and($localDeveloper->followers()->count())->toBe(5);
});

it('reuses an existing local developer', function (): void {
    $localDeveloper = User::factory()->create(['github_id' => 'local']);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('github_id', 'local')->sole()->id)->toBe($localDeveloper->id)
        ->and($localDeveloper->following()->count())->toBe(3)
        ->and($localDeveloper->followers()->count())->toBe(5);
});
