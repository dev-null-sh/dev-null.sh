<?php

declare(strict_types=1);

use App\Actions\CreateCommitImages;
use App\Models\Commit;
use App\Models\CommitImage;
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

/**
 * A minimal display RGB profile: a valid 128-byte header over recognisable filler, so tests can tell
 * whether the stored file carries these exact bytes.
 */
function imageColorProfile(int $bytes = 132): string
{
    $profile = str_repeat("\x00", $bytes);
    $profile = substr_replace($profile, pack('N', $bytes), 0, 4);
    $profile = substr_replace($profile, 'mntrRGB XYZ ', 12, 12);
    $profile = substr_replace($profile, 'acsp', 36, 4);

    return substr_replace($profile, 'devnull-test-profile', 100, 20);
}

function jpegWithColorProfile(string $profile): UploadedFile
{
    $source = imagecreatetruecolor(120, 80);
    imagefilledrectangle($source, 0, 0, 119, 79, (int) imagecolorallocate($source, 255, 0, 0));

    ob_start();
    imagejpeg($source, null, 100);
    $contents = (string) ob_get_clean();

    $chunks = mb_str_split($profile, 65_000, '8bit');
    $segments = '';

    foreach ($chunks as $index => $chunk) {
        $segment = "ICC_PROFILE\0".chr($index + 1).chr(count($chunks)).$chunk;
        $segments .= "\xFF\xE2".pack('n', mb_strlen($segment, '8bit') + 2).$segment;
    }

    return UploadedFile::fake()->createWithContent('photo.jpg', substr_replace($contents, $segments, 2, 0));
}

function pngWithColorProfile(string $contents, bool $alpha = false): UploadedFile
{
    $source = imagecreatetruecolor(120, 80);
    imagealphablending($source, false);
    imagesavealpha($source, true);
    $color = $alpha ? imagecolorallocatealpha($source, 255, 0, 0, 64) : imagecolorallocate($source, 255, 0, 0);
    imagefilledrectangle($source, 0, 0, 119, 79, (int) $color);

    ob_start();
    imagepng($source);
    $png = (string) ob_get_clean();

    $data = "profile\0\0".$contents;
    $chunk = pack('N', mb_strlen($data, '8bit')).'iCCP'.$data.pack('N', crc32('iCCP'.$data));

    return UploadedFile::fake()->createWithContent('screenshot.png', substr_replace($png, $chunk, 33, 0));
}

function webpWithColorProfile(string $profile): UploadedFile
{
    $source = imagecreatetruecolor(120, 80);
    imagefilledrectangle($source, 0, 0, 119, 79, (int) imagecolorallocate($source, 255, 0, 0));

    ob_start();
    imagewebp($source, null, 100);
    $webp = (string) ob_get_clean();

    $chunk = fn (string $type, string $data): string => $type.pack('V', mb_strlen($data, '8bit')).$data.(mb_strlen($data, '8bit') % 2 === 1 ? "\x00" : '');
    $vp8x = "\x20\0\0\0".mb_substr(pack('V', 119), 0, 3, '8bit').mb_substr(pack('V', 79), 0, 3, '8bit');
    $payload = 'WEBP'.$chunk('VP8X', $vp8x).$chunk('ICCP', $profile).mb_substr($webp, 12, null, '8bit');

    return UploadedFile::fake()->createWithContent('image.webp', 'RIFF'.pack('V', mb_strlen($payload, '8bit')).$payload);
}

/**
 * The color profile carried by the stored WebP, if any.
 */
function storedColorProfile(Commit $commit): ?string
{
    $stored = (string) Storage::disk()->get($commit->images()->sole()->path);
    $offset = 12;

    while ($offset + 8 <= mb_strlen($stored, '8bit')) {
        $size = unpack('V', mb_substr($stored, $offset + 4, 4, '8bit'))[1];

        if (mb_substr($stored, $offset, 4, '8bit') === 'ICCP') {
            return mb_substr($stored, $offset + 8, $size, '8bit');
        }

        $offset += 8 + $size + $size % 2;
    }

    return null;
}

it('keeps the color profile of uploads so their colors are read as authored', function (callable $file): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [$file()]);

    $image = $commit->images()->sole();
    $stored = (string) Storage::disk()->get($image->path);

    expect(storedColorProfile($commit))->toBe(imageColorProfile())
        ->and($image->only(['width', 'height']))->toBe(['width' => 120, 'height' => 80])
        ->and(getimagesizefromstring($stored)['mime'] ?? null)->toBe('image/webp')
        ->and(imagecreatefromstring($stored))->not->toBeFalse();
})->with([
    'a jpeg profile' => [fn (): UploadedFile => jpegWithColorProfile(imageColorProfile())],
    'a png profile' => [fn (): UploadedFile => pngWithColorProfile((string) gzcompress(imageColorProfile()))],
    'the profile of a transparent png' => [fn (): UploadedFile => pngWithColorProfile((string) gzcompress(imageColorProfile()), alpha: true)],
    'a webp profile' => [fn (): UploadedFile => webpWithColorProfile(imageColorProfile())],
]);

it('keeps a color profile too large for one jpeg segment', function (): void {
    $profile = imageColorProfile(70_000);
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [jpegWithColorProfile($profile)]);

    expect(storedColorProfile($commit))->toBe($profile);
});

it('keeps a color profile of an odd length', function (): void {
    $profile = imageColorProfile(129);
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [jpegWithColorProfile($profile)]);

    expect(storedColorProfile($commit))->toBe($profile);
});

it('drops a color profile that is not a plausible display profile', function (callable $mutate): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [jpegWithColorProfile($mutate(imageColorProfile()))]);

    $stored = (string) Storage::disk()->get($commit->images()->sole()->path);

    expect(storedColorProfile($commit))->toBeNull()
        ->and($stored)->not->toContain('devnull-test-profile');
})->with([
    'a bad signature' => [fn (string $profile): string => substr_replace($profile, 'xxxx', 36, 4)],
    'a declared size that does not match' => [fn (string $profile): string => substr_replace($profile, pack('N', 999), 0, 4)],
    'a print profile' => [fn (string $profile): string => substr_replace($profile, 'prtr', 12, 4)],
    'a non-rgb profile' => [fn (string $profile): string => substr_replace($profile, 'CMYK', 16, 4)],
    'a truncated profile' => [fn (string $profile): string => mb_substr($profile, 0, 64, '8bit')],
    'an oversized profile' => [fn (): string => imageColorProfile(1_048_577)],
]);

it('drops a png color profile that does not inflate', function (): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [pngWithColorProfile('not zlib at all')]);

    expect(storedColorProfile($commit))->toBeNull();
});

it('leaves images without a color profile untagged', function (string $name): void {
    $commit = Commit::factory()->create();

    resolve(CreateCommitImages::class)->handle($commit, [UploadedFile::fake()->image($name, 120, 80)]);

    expect(storedColorProfile($commit))->toBeNull();
})->with(['photo.jpg', 'screenshot.png', 'image.webp']);

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
