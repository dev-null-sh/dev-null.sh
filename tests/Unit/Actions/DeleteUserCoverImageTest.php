<?php

declare(strict_types=1);

use App\Actions\DeleteUserCoverImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
});

it('erases the cover and its file', function (): void {
    Storage::disk()->put('cover-images/cover.webp', 'cover');
    $user = User::factory()->create(['cover_image_path' => 'cover-images/cover.webp', 'cover_image_approved_at' => now()]);

    resolve(DeleteUserCoverImage::class)->handle($user);

    expect($user->refresh()->cover_image_path)->toBeNull()
        ->and($user->cover_image_approved_at)->toBeNull()
        ->and(Storage::disk()->exists('cover-images/cover.webp'))->toBeFalse();
});

it('leaves a cover uploaded after the one a moderator rejected', function (): void {
    Storage::disk()->put('cover-images/new.webp', 'cover');
    $user = User::factory()->create(['cover_image_path' => 'cover-images/new.webp']);

    resolve(DeleteUserCoverImage::class)->handle($user, 'cover-images/old.webp');

    expect($user->refresh()->cover_image_path)->toBe('cover-images/new.webp')
        ->and(Storage::disk()->exists('cover-images/new.webp'))->toBeTrue();
});

it('does nothing for a user without a cover', function (): void {
    $user = User::factory()->create();

    resolve(DeleteUserCoverImage::class)->handle($user);

    expect($user->refresh()->cover_image_path)->toBeNull();
});
