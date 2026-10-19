<?php

declare(strict_types=1);

use App\Models\Branch;
use Illuminate\Support\Facades\Http;

it('refreshes site meta for verified branches', function (): void {
    $image = imagecreatetruecolor(16, 16);
    $red = imagecolorallocate($image, 220, 40, 40);
    imagefilledrectangle($image, 0, 0, 15, 15, $red);
    ob_start();
    imagepng($image);
    $png = ob_get_clean();
    imagedestroy($image);

    Http::fake([
        'https://example.com/*' => Http::response(
            '<html><meta property="og:description" content="Example"><meta property="og:image" content="https://example.com/og.png"></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
        'www.google.com/s2/favicons*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        '*' => Http::response('', 404),
    ]);

    Branch::factory()->verified('example.com')->create([
        'name' => 'example',
        'brand_color' => null,
        'description' => null,
        'og_image_url' => null,
    ]);

    $this->artisan('branches:refresh-site-meta')
        ->assertSuccessful();

    $branch = Branch::query()->where('name', 'example')->firstOrFail();

    expect($branch->description)->toBe('Example')
        ->and($branch->og_image_url)->toBe('https://example.com/og.png')
        ->and($branch->brand_color)->toStartWith('#');
});

it('tells you when nothing is verified yet', function (): void {
    Branch::factory()->create(['name' => 'orphan']);

    $this->artisan('branches:refresh-site-meta')
        ->expectsOutputToContain('No verified branches')
        ->assertSuccessful();
});

it('refuses an unverified named branch', function (): void {
    Branch::factory()->create(['name' => 'orphan']);

    $this->artisan('branches:refresh-site-meta', ['branch' => 'orphan'])
        ->expectsOutputToContain('is not verified')
        ->assertFailed();
});
