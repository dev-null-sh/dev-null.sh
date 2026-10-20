<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
    config(['app.admins' => ['admin']]);
});

function coverModerator(): User
{
    return User::factory()->create(['username' => 'admin']);
}

function pendingCover(string $name = 'cover'): User
{
    Storage::disk()->put("cover-images/{$name}.webp", 'cover');

    return User::factory()->create(['cover_image_path' => "cover-images/{$name}.webp"]);
}

it('lists pending covers oldest first for moderators', function (): void {
    $newer = pendingCover('019a0000-0000-7000-8000-000000000002');
    $older = pendingCover('019a0000-0000-7000-8000-000000000001');
    User::factory()->create(['cover_image_path' => 'cover-images/approved.webp', 'cover_image_approved_at' => now()]);
    User::factory()->create();

    $this->actingAs(coverModerator())
        ->get(route('pending-cover-image.index'))
        ->assertInertia(fn ($page) => $page
            ->component('moderation/covers')
            ->has('covers.data', 2)
            ->where('covers.data.0.user.id', $older->id)
            ->where('covers.data.0.version', $older->coverImageVersion())
            ->where('covers.data.1.user.id', $newer->id)
            ->where('covers.data.1.url', fn (string $url): bool => str_contains($url, $newer->username)));
});

it('keeps the queues for moderators', function (string $method, string $route): void {
    $user = pendingCover();

    $this->actingAs(User::factory()->create())
        ->{$method}(route($route, $user), ['version' => $user->coverImageVersion()])
        ->assertForbidden();

    expect($user->refresh()->cover_image_path)->not->toBeNull()
        ->and($user->cover_image_approved_at)->toBeNull();
})->with([
    ['get', 'pending-cover-image.index'],
    ['post', 'user-cover-image-approval.store'],
    ['post', 'user-cover-image-rejection.store'],
]);

it('approves a pending cover', function (): void {
    $user = pendingCover();

    $this->actingAs(coverModerator())
        ->fromRoute('pending-cover-image.index')
        ->post(route('user-cover-image-approval.store', $user), ['version' => $user->coverImageVersion()])
        ->assertRedirectToRoute('pending-cover-image.index');

    expect($user->refresh()->cover_image_approved_at)->not->toBeNull();
});

it('rejects a cover by erasing it', function (bool $approved): void {
    $user = pendingCover();
    User::query()->whereKey($user->id)->update(['cover_image_approved_at' => $approved ? now() : null]);

    $this->actingAs(coverModerator())
        ->post(route('user-cover-image-rejection.store', $user), ['version' => $user->coverImageVersion()])
        ->assertRedirect();

    expect($user->refresh()->cover_image_path)->toBeNull()
        ->and(Storage::disk()->exists('cover-images/cover.webp'))->toBeFalse();
})->with([true, false]);

it('leaves alone a cover replaced since the moderator saw it', function (string $route): void {
    $user = pendingCover();

    $this->actingAs(coverModerator())
        ->post(route($route, $user), ['version' => 'stale'])
        ->assertNotFound();

    expect($user->refresh()->cover_image_path)->not->toBeNull()
        ->and($user->cover_image_approved_at)->toBeNull();
})->with(['user-cover-image-approval.store', 'user-cover-image-rejection.store']);

it('cannot approve a cover twice or reject a missing one', function (): void {
    $approved = User::factory()->create(['cover_image_path' => 'cover-images/approved.webp', 'cover_image_approved_at' => now()]);
    $without = User::factory()->create();

    $this->actingAs(coverModerator())
        ->post(route('user-cover-image-approval.store', $approved), ['version' => $approved->coverImageVersion()])
        ->assertNotFound();

    $this->post(route('user-cover-image-rejection.store', $without), ['version' => 'x'])->assertNotFound();
});
