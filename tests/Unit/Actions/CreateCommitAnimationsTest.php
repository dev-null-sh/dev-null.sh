<?php

declare(strict_types=1);

use App\Actions\CreateCommit;
use App\Actions\CreateCommitAnimations;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;

beforeEach(function (): void {
    Storage::fake();
});

function preparedAnimation(): array
{
    return [
        'file' => UploadedFile::fake()->createWithContent('animation.webm', mediaFixture('animated.webm')),
        'width' => 32,
        'height' => 24,
        'poster' => Image::fromUpload(UploadedFile::fake()->createWithContent('poster.png', mediaFixture('poster.png')))
            ->usingGd()->optimize('webp', 60),
    ];
}

it('stores validated animation data and its WebP poster', function (): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitAnimations::class)->handle($commit, preparedAnimation(), 'Animated colors', 2);

    $image = $commit->images()->sole();
    expect($image->position)->toBe(2)
        ->and($image->description)->toBe('Animated colors')
        ->and($image->duration_ms)->toBeNull()
        ->and($image->isAnimation())->toBeTrue()
        ->and(Storage::get($image->path))->toBe(mediaFixture('animated.webm'))
        ->and(getimagesizefromstring(Storage::get($image->previewPath()))['mime'])->toBe('image/webp');
});

it('leaves no record or orphaned video when poster storage fails', function (string $failure): void {
    $commit = Commit::factory()->create();
    $disk = Storage::disk();
    Storage::partialMock()->shouldReceive('put')->andReturnUsing(function (string $path, string $contents) use ($disk, $failure): bool {
        if (str_ends_with($path, '.poster.webp')) {
            return match ($failure) {
                'exception' => throw UnableToWriteFile::atLocation($path),
                'false' => false,
            };
        }

        return $disk->put($path, $contents);
    });
    Storage::shouldReceive('delete')->andReturnUsing($disk->delete(...));
    Storage::shouldReceive('disk')->andReturn($disk);

    expect(fn () => resolve(CreateCommitAnimations::class)->handle($commit, preparedAnimation(), null, 0))
        ->toThrow(match ($failure) {
            'exception' => UnableToWriteFile::class,
            'false' => RuntimeException::class,
        });
    expect($commit->images()->count())->toBe(0)
        ->and($disk->allFiles())->toBeEmpty();
})->with(['exception', 'false']);

it('sets content types and immutable public caching on the video and poster', function (): void {
    $commit = Commit::factory()->create();
    $disk = Storage::disk();
    $metadata = [];
    Storage::partialMock()->shouldReceive('put')->andReturnUsing(function (string $path, string $contents, array $options) use ($disk, &$metadata): bool {
        $metadata[$path] = $options;

        return $disk->put($path, $contents, $options);
    });
    Storage::shouldReceive('disk')->andReturn($disk);

    resolve(CreateCommitAnimations::class)->handle($commit, preparedAnimation(), null, 0);

    $image = $commit->images()->sole();
    expect($metadata[$image->path])->toBe([
        'ContentType' => 'video/webm',
        'ContentDisposition' => 'inline',
        'CacheControl' => 'public, max-age=31536000, immutable',
    ])->and($metadata[$image->previewPath()])->toBe([
        'ContentType' => 'image/webp',
        'ContentDisposition' => 'inline',
        'CacheControl' => 'public, max-age=31536000, immutable',
    ]);
});

it('removes earlier attachments when a later animation cannot be stored', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $disk = Storage::disk();
    $posters = 0;
    Storage::partialMock()->shouldReceive('put')->andReturnUsing(function (string $path, string $contents) use ($disk, &$posters): bool {
        if (str_ends_with($path, '.poster.webp') && ++$posters === 2) {
            return false;
        }

        return $disk->put($path, $contents);
    });
    Storage::shouldReceive('delete')->andReturnUsing($disk->delete(...));
    Storage::shouldReceive('disk')->andReturn($disk);

    expect(fn () => resolve(CreateCommit::class)->handle(
        $user,
        'Multiple attachments',
        images: [UploadedFile::fake()->image('still.png', 10, 10)],
        animations: [1 => preparedAnimation(), 2 => preparedAnimation()],
    ))->toThrow(RuntimeException::class, 'Unable to store animation file.');

    expect($disk->allFiles())->toBeEmpty()
        ->and($user->commits()->count())->toBe(0)
        ->and(CommitImage::query()->count())->toBe(0);
});

it('removes attachments when an enclosing transaction rolls back', function (): void {
    Queue::fake();
    $user = User::factory()->create();

    expect(fn () => DB::transaction(function () use ($user): void {
        resolve(CreateCommit::class)->handle(
            $user,
            'First part of a series',
            images: [UploadedFile::fake()->image('still.png', 10, 10)],
            animations: [1 => preparedAnimation()],
        );
        expect(Storage::allFiles())->toHaveCount(3);

        throw new RuntimeException('Later part failed.');
    }))->toThrow(RuntimeException::class, 'Later part failed.');

    expect(Storage::allFiles())->toBeEmpty()
        ->and($user->commits()->count())->toBe(0)
        ->and(CommitImage::query()->count())->toBe(0);
});
