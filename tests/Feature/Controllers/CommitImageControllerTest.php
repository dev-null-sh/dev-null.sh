<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Illuminate\Filesystem\AwsS3V3Adapter;
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

it('serves images of approved commits to everyone', function (): void {
    $image = storedCommitImage(Commit::factory()->create());

    $response = $this->get(route('commit-image.show', $image));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox; frame-ancestors 'none'");

    expect($response->headers->get('Content-Disposition'))->toBe('inline')
        ->and($response->streamedContent())->toBe(Storage::disk()->get($image->path));
});

it('hides images of pending commits from guests and other users', function (): void {
    $image = storedCommitImage(Commit::factory()->pending()->create());

    $this->get(route('commit-image.show', $image))->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('commit-image.show', $image))
        ->assertNotFound();
});

it('hides images of rejected commits from other users', function (): void {
    $image = storedCommitImage(Commit::factory()->rejected()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('commit-image.show', $image))
        ->assertNotFound();
});

it('serves images of pending commits to their author', function (): void {
    $commit = Commit::factory()->pending()->create();
    $image = storedCommitImage($commit);

    $this->actingAs($commit->user)
        ->get(route('commit-image.show', $image))
        ->assertOk();
});

it('serves images of pending commits to moderators', function (): void {
    config(['app.admins' => ['admin']]);

    $image = storedCommitImage(Commit::factory()->pending()->create());

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->get(route('commit-image.show', $image))
        ->assertOk();
});

it('hides images of deleted commits', function (): void {
    $commit = Commit::factory()->create();
    $image = storedCommitImage($commit);

    $commit->delete();

    $this->actingAs($commit->user)
        ->get(route('commit-image.show', $image))
        ->assertNotFound();
});

it('responds not found when the image file is missing from the disk', function (): void {
    $image = CommitImage::factory()->for(Commit::factory())->create();

    $this->get(route('commit-image.show', $image))->assertNotFound();
});

it('does not cache images publicly', function (): void {
    $image = storedCommitImage(Commit::factory()->create());

    $response = $this->get(route('commit-image.show', $image));

    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');
});

it('redirects to a signed cloud storage url instead of streaming the image', function (): void {
    $image = CommitImage::factory()->for(Commit::factory())->create();

    $disk = Mockery::mock(AwsS3V3Adapter::class);
    $disk->shouldNotReceive('exists');
    $disk->shouldReceive('temporaryUrl')
        ->once()
        ->with($image->path, Mockery::type(DateTimeInterface::class), [
            'ResponseContentType' => 'image/webp',
            'ResponseContentDisposition' => 'inline',
            'ResponseCacheControl' => 'private, max-age=3600',
        ])
        ->andReturn('https://bucket.example.com/'.$image->path.'?X-Amz-Signature=abc');
    Storage::set(config('filesystems.default'), $disk);

    $response = $this->get(route('commit-image.show', $image));

    $response->assertRedirect('https://bucket.example.com/'.$image->path.'?X-Amz-Signature=abc');

    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');
});

it('does not redirect to cloud storage for images the viewer may not see', function (): void {
    $image = CommitImage::factory()->for(Commit::factory()->pending())->create();

    $disk = Mockery::mock(AwsS3V3Adapter::class);
    $disk->shouldNotReceive('temporaryUrl');
    Storage::set(config('filesystems.default'), $disk);

    $this->get(route('commit-image.show', $image))->assertNotFound();
});
