<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
});

function storedCommitImage(Commit $commit): CommitImage
{
    $path = 'commit-images/'.fake()->uuid().'.webp';

    Storage::disk()->put($path, (string) UploadedFile::fake()->image('image.webp', 10, 10)->getContent());

    return CommitImage::factory()->for($commit)->create(['path' => $path]);
}

it('serves images of approved commits to everyone', function (array $query): void {
    $image = storedCommitImage(Commit::factory()->create());

    $response = $this->get(route('commit-image.show', ['commitImage' => $image, ...$query]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox; frame-ancestors 'none'");

    expect($response->headers->get('Content-Disposition'))->toBe('inline; filename='.$image->id.'.webp')
        ->and($response->streamedContent())->toBe(Storage::disk()->get($image->path));
})->with(['default' => [[]], 'download disabled' => [['download' => '0']]]);

it('downloads the stored image with a webp filename', function (): void {
    $image = storedCommitImage(Commit::factory()->create());

    $response = $this->get(route('commit-image.show', ['commitImage' => $image, 'download' => 1]));

    $response->assertOk()
        ->assertDownload($image->id.'.webp')
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox; frame-ancestors 'none'")
        ->assertStreamedContent(Storage::disk()->get($image->path));

    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');
});

it('hides images of pending commits from guests and other users', function (bool $download): void {
    $image = storedCommitImage(Commit::factory()->pending()->create());
    $url = route('commit-image.show', ['commitImage' => $image, 'download' => $download]);

    $this->get($url)->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get($url)
        ->assertNotFound();
})->with([false, true]);

it('hides images of rejected commits from other users', function (): void {
    $image = storedCommitImage(Commit::factory()->rejected()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('commit-image.show', $image))
        ->assertNotFound();
});

it('serves images of pending commits to their author', function (bool $download): void {
    $commit = Commit::factory()->pending()->create();
    $image = storedCommitImage($commit);

    $this->actingAs($commit->user)
        ->get(route('commit-image.show', ['commitImage' => $image, 'download' => $download]))
        ->assertOk();
})->with([false, true]);

it('serves images of pending commits to moderators', function (): void {
    config(['app.admins' => ['admin']]);

    $image = storedCommitImage(Commit::factory()->pending()->create());

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->get(route('commit-image.show', $image))
        ->assertOk();
});

it('serves images of pending and rejected commits through a temporary url, until it expires', function (string $state): void {
    $image = storedCommitImage(Commit::factory()->{$state}()->create());
    $url = $image->temporaryUrl();

    $this->get($url)->assertOk();
    $this->get($url.'&download=1')->assertNotFound();

    $this->travel(2)->hours();

    $this->get($url)->assertNotFound();
})->with(['pending', 'rejected']);

it('hides images of deleted commits behind a temporary url', function (): void {
    $commit = Commit::factory()->pending()->create();
    $image = storedCommitImage($commit);
    $url = $image->temporaryUrl();

    $commit->delete();

    $this->get($url)->assertNotFound();
});

it('hides images of deleted commits', function (): void {
    $commit = Commit::factory()->create();
    $image = storedCommitImage($commit);

    $commit->delete();

    $this->actingAs($commit->user)
        ->get(route('commit-image.show', $image))
        ->assertNotFound();
});

it('responds not found when the image file is missing from the disk', function (bool $download): void {
    $image = CommitImage::factory()->for(Commit::factory())->create();

    $this->get(route('commit-image.show', ['commitImage' => $image, 'download' => $download]))->assertNotFound();
})->with([false, true]);

it('does not cache images publicly', function (): void {
    $image = storedCommitImage(Commit::factory()->create());

    $response = $this->get(route('commit-image.show', $image));

    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');
});
