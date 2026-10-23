<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake();
});

it('serves screenshots anonymously without caching them after account deletion', function (): void {
    $reporter = Str::uuid7()->toString();
    $image = Str::uuid7()->toString();
    $bytes = UploadedFile::fake()->image('shot.webp')->getContent();
    Storage::disk()->put("issue-images/{$reporter}/{$image}.webp", $bytes);

    $response = $this->get(route('issue-image.show', ['reporter' => $reporter, 'image' => $image]));
    $response->assertOk()->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox; frame-ancestors 'none'")
        ->assertStreamedContent($bytes);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    Storage::disk()->deleteDirectory('issue-images/'.$reporter);
    $this->get(route('issue-image.show', ['reporter' => $reporter, 'image' => $image]))->assertNotFound();
});

it('returns not found for missing or invalid screenshot references', function (string $reporter, string $image): void {
    $this->get('/issue-images/'.$reporter.'/'.$image)->assertNotFound();
})->with([
    'missing' => ['019a0000-0000-7000-8000-000000000000', '019a0000-0000-7000-8000-000000000001'],
    'invalid reporter' => ['invalid', '019a0000-0000-7000-8000-000000000001'],
    'invalid image' => ['019a0000-0000-7000-8000-000000000000', 'invalid'],
    'traversal' => ['019a0000-0000-7000-8000-000000000000', '..%2Fsecret'],
]);
