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

it('does nothing for a user without a cover', function (): void {
    $user = User::factory()->create();

    resolve(DeleteUserCoverImage::class)->handle($user);

    expect($user->refresh()->cover_image_path)->toBeNull();
});
