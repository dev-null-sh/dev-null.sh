<?php

declare(strict_types=1);

use App\Models\Branch;
use Database\Seeders\ExampleBranchesSeeder;
use Illuminate\Support\Facades\Http;

it('verifies example branches and refreshes site meta', function (): void {
    $image = imagecreatetruecolor(16, 16);
    $red = imagecolorallocate($image, 220, 40, 40);
    imagefilledrectangle($image, 0, 0, 15, 15, $red);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);

    Http::fake([
        'https://laravel.com/*' => Http::response(
            '<html><meta property="og:description" content="Laravel"><meta property="og:image" content="https://laravel.com/og.png"></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'www.google.com/s2/favicons*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        '*' => Http::response(
            '<html><meta property="og:description" content="Demo"><meta property="og:image" content="https://example.com/og.png"></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    // Pre-existing unverified row from commit backfill / upsert.
    Branch::factory()->create(['name' => 'laravel']);

    $this->seed(ExampleBranchesSeeder::class);

    $laravel = Branch::query()->where('name', 'laravel')->firstOrFail();

    expect($laravel->isVerified())->toBeTrue()
        ->and($laravel->verified_domain)->toBe('laravel.com')
        ->and($laravel->brand_color)->toStartWith('#')
        ->and($laravel->og_image_url)->toBe('https://laravel.com/og.png')
        ->and($laravel->description)->toBe('Laravel')
        ->and($laravel->members()->count())->toBeGreaterThan(0);

    expect(Branch::query()->whereNotNull('verified_at')->count())->toBeGreaterThan(5);
});
