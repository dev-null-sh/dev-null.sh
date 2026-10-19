<?php

declare(strict_types=1);

use App\Actions\RefreshBranchSiteMeta;
use App\Models\Branch;
use App\Services\BranchStyles;
use Illuminate\Support\Facades\Http;

it('refreshes description, og image, and brand colour from the verified domain', function (): void {
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

    $branch = Branch::factory()->verified('example.com')->create([
        'name' => 'example',
        'brand_color' => '#0000ff',
        'description' => null,
    ]);

    $refreshed = resolve(RefreshBranchSiteMeta::class)->handle($branch);

    expect($refreshed->domain_description)->toBe('Example')
        ->and($refreshed->description)->toBe('Example')
        ->and($refreshed->og_image_url)->toBe('https://example.com/og.png')
        ->and($refreshed->brand_color)->toStartWith('#')
        ->and($refreshed->brand_color)->not->toBe('#0000ff');
});

it('refreshes every verified branch', function (): void {
    Http::fake([
        '*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    Branch::factory()->verified('one.test')->create(['name' => 'one']);
    Branch::factory()->verified('two.test')->create(['name' => 'two']);
    Branch::factory()->create(['name' => 'unverified']);

    expect(resolve(RefreshBranchSiteMeta::class)->handleVerified())->toBe(2);
});

it('clears the branch styles cache after a refresh', function (): void {
    Http::fake([
        '*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $branch = Branch::factory()->verified('example.com')->create(['name' => 'example', 'brand_color' => '#111111']);
    $styles = resolve(BranchStyles::class);
    expect($styles->color('example'))->toBe('#111111');

    resolve(RefreshBranchSiteMeta::class)->handle($branch->refresh());

    expect($styles->color('example'))->toBeNull();
});
