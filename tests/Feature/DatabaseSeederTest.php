<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
});

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

it('seeds replies and images that exist on the disk', function (): void {
    $this->seed(DatabaseSeeder::class);

    $images = CommitImage::query()->get();

    expect(Commit::query()->whereNotNull('parent_id')->exists())->toBeTrue()
        ->and($images)->not->toBeEmpty()
        ->and($images->every(fn (CommitImage $image): bool => Storage::exists($image->path)))->toBeTrue();

    $image = $images->first();
    $size = getimagesizefromstring((string) Storage::get($image->path));

    expect($size)->toMatchArray([0 => $image->width, 1 => $image->height, 'mime' => 'image/webp']);
});
