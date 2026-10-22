<?php

declare(strict_types=1);

use App\Actions\UpdateUserCoverImage;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake();
    config(['app.admins' => ['admin']]);
});

it('crops the cover to a 3.5:1 webp banner', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserCoverImage::class)->handle($user, UploadedFile::fake()->image('cover.jpg', 2000, 2000));

    $path = (string) $user->refresh()->cover_image_path;
    $size = getimagesizefromstring((string) Storage::disk()->get($path));

    expect($path)->toStartWith('cover-images/')->toEndWith('.webp')
        ->and($size)->toMatchArray([0 => 1750, 1 => 500, 'mime' => 'image/webp']);
});

it('crops at the offset the author dragged the cover to', function (int $width, int $height, bool $vertical, float $offsetX, float $offsetY, string $color): void {
    $user = User::factory()->create();

    resolve(UpdateUserCoverImage::class)->handle($user, twoToneCover($width, $height, $vertical), $offsetX, $offsetY);

    expect(coverCenterColor($user))->toBe($color);
})->with([
    'tall, dragged to the top' => [1500, 1500, true, 0.5, 0.0, 'red'],
    'tall, dragged to the bottom' => [1500, 1500, true, 0.5, 1.0, 'blue'],
    'wide, dragged to the left' => [6000, 500, false, 0.0, 0.5, 'red'],
    'wide, dragged to the right' => [6000, 500, false, 1.0, 0.5, 'blue'],
]);

it('leaves covers of new accounts waiting for a moderator', function (): void {
    $user = User::factory()->create();

    resolve(UpdateUserCoverImage::class)->handle($user, UploadedFile::fake()->image('cover.png', 900, 300));

    expect($user->refresh()->cover_image_path)->not->toBeNull()
        ->and($user->cover_image_approved_at)->toBeNull()
        ->and($user->coverImageIsPending())->toBeTrue();
});

it('approves covers at once for admins and authors with a good reputation', function (string $username): void {
    $user = User::factory()->create(['username' => $username, 'created_at' => now()->subHours(2)]);
    Commit::factory()->for($user)->create();
    followersOf($user, 10);

    resolve(UpdateUserCoverImage::class)->handle($user, UploadedFile::fake()->image('cover.png', 900, 300));

    expect($user->refresh()->cover_image_approved_at)->not->toBeNull();
})->with(['admin', 'reputable']);

it('erases the previous cover when it is replaced', function (): void {
    $user = User::factory()->create();
    $action = resolve(UpdateUserCoverImage::class);

    $action->handle($user, UploadedFile::fake()->image('first.png', 900, 300));

    $first = $user->refresh()->cover_image_path;

    $action->handle($user, UploadedFile::fake()->image('second.png', 900, 300));

    expect(Storage::disk()->allFiles('cover-images'))->toBe([$user->refresh()->cover_image_path])
        ->and($user->cover_image_path)->not->toBe($first);
});

it('does not change the updated time of the user', function (): void {
    $user = User::factory()->create(['updated_at' => now()->subDay()]);

    resolve(UpdateUserCoverImage::class)->handle($user, UploadedFile::fake()->image('cover.png', 900, 300));

    expect($user->refresh()->updated_at->isToday())->toBeFalse();
});

it('refuses images that cannot be decoded and stores nothing', function (): void {
    $user = User::factory()->create();
    $contents = (string) UploadedFile::fake()->image('broken.png', 900, 300)->getContent();

    expect(fn () => resolve(UpdateUserCoverImage::class)->handle($user, UploadedFile::fake()->createWithContent('broken.png', substr_replace($contents, '', 40))))
        ->toThrow(ValidationException::class);

    expect($user->refresh()->cover_image_path)->toBeNull()
        ->and(Storage::disk()->allFiles())->toBe([]);
});
