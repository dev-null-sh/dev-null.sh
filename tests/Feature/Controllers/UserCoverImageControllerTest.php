<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
    config(['app.admins' => ['admin']]);
});

function userWithCover(bool $approved = true): User
{
    $path = 'cover-images/'.fake()->uuid().'.webp';

    Storage::disk()->put($path, (string) UploadedFile::fake()->image('cover.webp', 30, 10)->getContent());

    return User::factory()->create(['cover_image_path' => $path, 'cover_image_approved_at' => $approved ? now() : null]);
}

it('serves approved covers to everyone', function (): void {
    $user = userWithCover();

    $response = $this->get((string) $user->coverImageUrl(null));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->streamedContent())->toBe(Storage::disk()->get((string) $user->cover_image_path));
});

it('hides pending covers from guests and other users', function (): void {
    $user = userWithCover(approved: false);
    $url = route('user-cover-image.show', $user);

    $this->get($url)->assertNotFound();

    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
});

it('serves pending covers to their owner and moderators', function (): void {
    $user = userWithCover(approved: false);

    $this->actingAs($user)->get(route('user-cover-image.show', $user))->assertOk();

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('user-cover-image.show', $user))
        ->assertOk();
});

it('is not found without a cover or its file', function (): void {
    $this->get(route('user-cover-image.show', User::factory()->create()))->assertNotFound();

    $missing = User::factory()->create(['cover_image_path' => 'cover-images/missing.webp', 'cover_image_approved_at' => now()]);

    $this->get(route('user-cover-image.show', $missing))->assertNotFound();
});

it('uploads a cover', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->put(route('user-cover-image.update'), ['cover_image' => UploadedFile::fake()->image('cover.jpg', 1500, 500), 'offset_x' => 0.5, 'offset_y' => 0.5])
        ->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->cover_image_path)->not->toBeNull()
        ->and(Storage::disk()->exists((string) $user->cover_image_path))->toBeTrue();
});

it('refuses uploads that are not usable covers', function (Closure $file): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson(route('user-cover-image.update'), ['cover_image' => $file()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cover_image');

    expect($user->refresh()->cover_image_path)->toBeNull();
})->with([
    'missing' => fn (): Closure => fn (): null => null,
    'too small' => fn (): Closure => fn (): UploadedFile => UploadedFile::fake()->image('cover.png', 600, 200),
    'too large' => fn (): Closure => fn (): UploadedFile => UploadedFile::fake()->image('cover.png', 6001, 500),
    'over 5 MB' => fn (): Closure => fn (): UploadedFile => UploadedFile::fake()->image('cover.jpg', 1500, 500)->size(5 * 1024 + 1),
    'gif' => fn (): Closure => fn (): UploadedFile => UploadedFile::fake()->image('cover.gif', 1500, 500),
    'not an image' => fn (): Closure => fn (): UploadedFile => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf'),
]);

it('crops the uploaded cover at the dragged offsets', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('user-cover-image.update'), ['cover_image' => twoToneCover(1500, 1500, vertical: true), 'offset_x' => '0.5', 'offset_y' => '1'])
        ->assertRedirect();

    expect(coverCenterColor($user))->toBe('blue');
});

it('refuses offsets outside the image', function (string $field, mixed $value): void {
    $this->actingAs(User::factory()->create())
        ->putJson(route('user-cover-image.update'), ['cover_image' => UploadedFile::fake()->image('cover.jpg', 1500, 500), $field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    ['offset_x', '-0.1'],
    ['offset_y', '1.5'],
    ['offset_y', 'top'],
    ['offset_x', null],
]);

it('removes a cover', function (): void {
    $user = userWithCover();
    $path = (string) $user->cover_image_path;

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->delete(route('user-cover-image.destroy'))
        ->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->cover_image_path)->toBeNull()
        ->and(Storage::disk()->exists($path))->toBeFalse();
});

it('requires authentication to change a cover', function (): void {
    $this->putJson(route('user-cover-image.update'))->assertUnauthorized();
    $this->deleteJson(route('user-cover-image.destroy'))->assertUnauthorized();
});

it('limits cover changes to five a minute', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($user)->deleteJson(route('user-cover-image.destroy'))->assertRedirect();
    }

    $this->actingAs($user)->deleteJson(route('user-cover-image.destroy'))->assertTooManyRequests();
});

it('shows the cover on the profile, and the pending notice only to its owner', function (): void {
    $user = userWithCover(approved: false);

    $this->get(route('user.show', $user))->assertInertia(fn ($page) => $page
        ->where('coverImageUrl', null)
        ->where('coverImagePending', false));

    $this->actingAs($user)->get(route('user.show', $user))->assertInertia(fn ($page) => $page
        ->where('coverImageUrl', $user->coverImageUrl($user))
        ->where('coverImagePending', true));

    $this->actingAs($user)->get(route('user-profile.edit'))->assertInertia(fn ($page) => $page
        ->where('coverImageUrl', $user->coverImageUrl($user))
        ->where('coverImagePending', true));
});
