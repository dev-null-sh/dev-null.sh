<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
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
        ->and($response->baseResponse->getFile()->getPathname())->toBe(Storage::disk()->path($image->path));
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

function storedCommitAnimation(Commit $commit): CommitImage
{
    $image = CommitImage::factory()->for($commit)->create([
        'path' => 'commit-images/'.fake()->uuid().'.webm',
        'mime_type' => 'video/webm',
        'duration_ms' => 470,
        'width' => 32,
        'height' => 24,
    ]);
    Storage::put($image->path, mediaFixture('animated.webm'));
    Storage::put($image->previewPath(), 'stored WebP poster');

    return $image;
}

it('serves private byte ranges for local animations', function (): void {
    $image = storedCommitAnimation(Commit::factory()->create());

    $response = $this->get(route('commit-image.show', $image), ['Range' => 'bytes=3-26']);

    $response->assertStatus(206)
        ->assertHeader('Content-Type', 'video/webm')
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Length', '24')
        ->assertHeader('Content-Range', 'bytes 3-26/'.mb_strlen(mediaFixture('animated.webm'), '8bit'));
    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public');

    ob_start();
    $response->baseResponse->sendContent();
    $content = (string) ob_get_clean();
    expect($content)->toBe(mb_substr(mediaFixture('animated.webm'), 3, 24, '8bit'));
});

it('redirects object-storage media to the configured public URL without reading their contents', function (string $media): void {
    $local = Storage::disk();
    $disk = Mockery::mock(new FilesystemAdapter($local->getDriver(), $local->getAdapter(), [
        'url' => 'https://media.example.test',
    ]));
    $disk->shouldNotReceive('get');
    Storage::set('test-object', $disk);
    config(['filesystems.default' => 'test-object']);
    $image = $media === 'image'
        ? storedCommitImage(Commit::factory()->create())
        : storedCommitAnimation(Commit::factory()->create());
    $poster = $media === 'poster';

    $response = $this->get(route('commit-image.show', [$image, 'poster' => $poster ? 1 : 0]));

    $path = $poster ? $image->previewPath() : $image->path;
    $response->assertRedirect('https://media.example.test/'.$path);
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store')->not->toContain('public');
})->with(['image', 'animation', 'poster']);

it('checks authorization before redirecting to public object storage', function (): void {
    $local = Storage::disk();
    Storage::set('test-object', new FilesystemAdapter($local->getDriver(), $local->getAdapter(), [
        'url' => 'https://media.example.test',
    ]));
    config(['filesystems.default' => 'test-object']);
    $commit = Commit::factory()->pending()->create();
    $image = storedCommitAnimation($commit);

    $this->get(route('commit-image.show', $image))->assertNotFound();
    $this->get(route('commit-image.show', [$image, 'poster' => 1]))->assertNotFound();
    $this->actingAs($commit->user)->get(route('commit-image.show', $image))
        ->assertRedirect('https://media.example.test/'.$image->path);
});

it('serves suffix ranges and rejects ranges beyond the file', function (): void {
    $image = storedCommitAnimation(Commit::factory()->create());
    $size = mb_strlen(mediaFixture('animated.webm'), '8bit');

    $this->get(route('commit-image.show', $image), ['Range' => 'bytes=-10'])
        ->assertStatus(206)
        ->assertHeader('Content-Length', '10')
        ->assertHeader('Content-Range', 'bytes '.($size - 10).'-'.($size - 1).'/'.$size);

    $this->get(route('commit-image.show', $image), ['Range' => 'bytes=999999-1000000'])
        ->assertStatus(416)
        ->assertHeader('Content-Range', 'bytes */'.$size);
});

it('returns video metadata without a body for HEAD requests', function (): void {
    $image = storedCommitAnimation(Commit::factory()->create());
    $response = $this->head(route('commit-image.show', $image));

    $response->assertOk()->assertHeader('Content-Type', 'video/webm');
    ob_start();
    $response->baseResponse->sendContent();
    expect((string) ob_get_clean())->toBe('');
});

it('authorizes animation posters like their video', function (): void {
    $commit = Commit::factory()->pending()->create();
    $image = storedCommitAnimation($commit);
    $video = route('commit-image.show', $image);
    $poster = route('commit-image.show', [$image, 'poster' => 1]);

    $this->get($video)->assertNotFound();
    $this->get($poster)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($poster)->assertNotFound();
    $this->actingAs($commit->user)->get($video)->assertOk();
    $this->get($poster)->assertOk()->assertHeader('Content-Type', 'image/webp');

    Storage::delete($image->previewPath());
    $this->get($poster)->assertNotFound();
});
