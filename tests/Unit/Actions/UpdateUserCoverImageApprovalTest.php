<?php

declare(strict_types=1);

use App\Actions\UpdateUserCoverImageApproval;
use App\Models\User;

it('approves the cover', function (): void {
    $user = User::factory()->create(['cover_image_path' => 'cover-images/cover.webp']);

    resolve(UpdateUserCoverImageApproval::class)->handle($user);

    expect($user->refresh()->cover_image_approved_at)->not->toBeNull();
});

it('leaves a cover uploaded after the one a moderator approved waiting for review', function (): void {
    $user = User::factory()->create(['cover_image_path' => 'cover-images/old.webp']);
    User::query()->whereKey($user->id)->update(['cover_image_path' => 'cover-images/new.webp']);

    resolve(UpdateUserCoverImageApproval::class)->handle($user);

    expect($user->refresh()->cover_image_approved_at)->toBeNull();
});
