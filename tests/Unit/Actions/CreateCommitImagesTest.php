<?php

declare(strict_types=1);

use App\Actions\CreateCommitImages;
use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake();
});

it('stores images of a commit as webp in order', function (): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [
        UploadedFile::fake()->image('first.jpg', 300, 200),
        UploadedFile::fake()->image('second.png', 100, 400),
    ]);

    $images = $commit->images;

    expect($images)->toHaveCount(2)
        ->and($images->pluck('position')->all())->toBe([0, 1])
        ->and($images->map->only(['width', 'height'])->all())->toBe([
            ['width' => 300, 'height' => 200],
            ['width' => 100, 'height' => 400],
        ]);

    $images->each(function (CommitImage $image): void {
        expect($image->path)->toStartWith('commit-images/')->toEndWith('.webp')
            ->and(getimagesizefromstring((string) Storage::disk()->get($image->path))['mime'] ?? null)->toBe('image/webp');
    });
});

it('fails when an image cannot be stored', function (): void {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->once()->andReturnFalse();

    $storage = Mockery::mock(FilesystemFactory::class);
    $storage->shouldReceive('disk')->once()->with(null)->andReturn($disk);
    $originalStorage = app()->make(FilesystemFactory::class);
    app()->instance(FilesystemFactory::class, $storage);

    $commit = Commit::factory()->create();

    try {
        expect(fn () => resolve(CreateCommitImages::class)->handle($commit, [
            UploadedFile::fake()->image('evidence.png'),
        ]))->toThrow(RuntimeException::class, 'Unable to store image.');
    } finally {
        app()->instance(FilesystemFactory::class, $originalStorage);
    }

    expect($commit->images()->count())->toBe(0);
});

it('drops anything smuggled alongside the pixels', function (): void {
    $file = UploadedFile::fake()->image('payload.png', 10, 10);
    file_put_contents($file->getRealPath(), '<?php echo "pwned"; ?>', FILE_APPEND);

    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [$file]);

    expect(Storage::disk()->get($commit->images()->sole()->path))->not->toContain('<?php');
});

it('applies the exif orientation of photos and drops their metadata', function (int $orientation, int $width, int $height, string $topLeft): void {
    $source = imagecreatetruecolor(300, 100);
    imagefilledrectangle($source, 0, 0, 149, 99, (int) imagecolorallocate($source, 255, 0, 0));
    imagefilledrectangle($source, 150, 0, 299, 99, (int) imagecolorallocate($source, 0, 0, 255));

    ob_start();
    imagejpeg($source, null, 100);
    $contents = (string) ob_get_clean();

    $exif = "Exif\0\0MM\0\x2a\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01\0".chr($orientation)."\0\0\0\0\0\0";
    $file = UploadedFile::fake()->createWithContent('photo.jpg', substr_replace($contents, "\xFF\xE1".pack('n', 34).$exif, 2, 0));

    expect(exif_read_data($file->getRealPath())['Orientation'] ?? null)->toBe($orientation);

    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [$file]);

    $image = $commit->images()->sole();
    $stored = (string) Storage::disk()->get($image->path);
    $color = imagecolorat(imagecreatefromstring($stored), 5, 5);

    expect($image->only(['width', 'height']))->toBe(['width' => $width, 'height' => $height])
        ->and(($color >> 16 & 0xFF) > ($color & 0xFF) ? 'red' : 'blue')->toBe($topLeft)
        ->and($stored)->not->toContain('Exif');
})->with([
    'upright' => [1, 300, 100, 'red'],
    'mirrored' => [2, 300, 100, 'blue'],
    'upside down' => [3, 300, 100, 'blue'],
    'upside down and mirrored' => [4, 300, 100, 'red'],
    'turned and mirrored' => [5, 100, 300, 'red'],
    'turned clockwise' => [6, 100, 300, 'red'],
    'turned anticlockwise and mirrored' => [7, 100, 300, 'blue'],
    'turned anticlockwise' => [8, 100, 300, 'blue'],
]);

it('refuses images that cannot be decoded', function (): void {
    $contents = (string) UploadedFile::fake()->image('broken.png', 10, 10)->getContent();

    resolve(CreateCommitImages::class)->handle(Commit::factory()->create(), [
        UploadedFile::fake()->createWithContent('broken.png', substr_replace($contents, '', 40)),
    ]);
})->throws(ValidationException::class);

it('scales large images down', function (): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [UploadedFile::fake()->image('wide.jpg', 4000, 1000)]);

    expect($commit->images()->sole()->only(['width', 'height']))->toBe(['width' => 1600, 'height' => 400]);
});

it('stores a far smaller file than the upload', function (): void {
    $source = imagecreatetruecolor(3000, 2000);

    for ($x = 0; $x < 3000; $x += 10) {
        imagefilledrectangle($source, $x, 0, $x + 9, 1999, (int) imagecolorallocate($source, $x % 256, ($x * 3) % 256, ($x * 7) % 256));
    }

    ob_start();
    imagejpeg($source, null, 100);
    $upload = UploadedFile::fake()->createWithContent('photo.jpg', (string) ob_get_clean());

    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [$upload]);

    expect(Storage::disk()->size($commit->images()->sole()->path))->toBeLessThan(intdiv((int) $upload->getSize(), 4));
});

it('stores at most three images', function (): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, array_map(
        fn (int $index): UploadedFile => UploadedFile::fake()->image("{$index}.png", 10, 10),
        range(1, 4),
    ));

    expect($commit->images()->count())->toBe(3);
});

it('refuses files that are not images, storing none of the images', function (): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [
        UploadedFile::fake()->image('valid.png', 10, 10),
        UploadedFile::fake()->createWithContent('fake.png', 'not an image'),
    ]);
})->throws(ValidationException::class);

it('refuses formats other than jpeg, png and webp', function (string $name): void {
    $file = UploadedFile::fake()->image($name, 10, 10);

    resolve(CreateCommitImages::class)->handle(Commit::factory()->create(), [$file]);
})->throws(ValidationException::class)->with(['image.bmp', 'image.gif']);

it('refuses images too large to decode safely', function (): void {
    resolve(CreateCommitImages::class)->handle(Commit::factory()->create(), [UploadedFile::fake()->image('huge.png', 6001, 10)]);
})->throws(ValidationException::class);

it('leaves nothing behind when an image is refused', function (): void {
    $commit = Commit::factory()->create();

    try {
        resolve(CreateCommitImages::class)->handle($commit, [
            UploadedFile::fake()->image('valid.png', 10, 10),
            UploadedFile::fake()->createWithContent('fake.png', 'not an image'),
        ]);
    } catch (ValidationException) {
        //
    }

    expect($commit->images()->count())->toBe(0)
        ->and(Storage::disk()->allFiles())->toBe([]);
});
