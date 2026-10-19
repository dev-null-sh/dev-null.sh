<?php

declare(strict_types=1);

use App\Services\FaviconBrandColor;
use Illuminate\Support\Facades\Http;

it('extracts a saturated colour from a favicon image', function (): void {
    $image = imagecreatetruecolor(16, 16);
    $red = imagecolorallocate($image, 220, 40, 40);
    imagefilledrectangle($image, 0, 0, 15, 15, $red);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);

    Http::fake([
        'www.google.com/s2/favicons*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    $color = (new FaviconBrandColor)->forDomain('laravel.com');

    expect($color)->toStartWith('#')
        ->and(mb_strlen($color))->toBe(7)
        ->and(hexdec(mb_substr($color, 1, 2)))->toBeGreaterThan(150)
        ->and(hexdec(mb_substr($color, 3, 2)))->toBeLessThan(100);
});

it('returns null when no favicon can be loaded', function (): void {
    Http::fake([
        '*' => Http::response('', 404),
    ]);

    expect((new FaviconBrandColor)->forDomain('missing.example'))->toBeNull();
});

it('falls back to near-black favicon colours when nothing saturated is present', function (): void {
    $image = imagecreatetruecolor(16, 16);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 15, 15, $black);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);

    Http::fake([
        'www.google.com/s2/favicons*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);

    expect((new FaviconBrandColor)->forDomain('vercel.com'))->toBe('#000000');
});
