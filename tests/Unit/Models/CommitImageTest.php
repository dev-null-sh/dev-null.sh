<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\CommitImage;

test('to array', function (): void {
    $image = CommitImage::factory()->create()->refresh();

    expect($image->toArray())->toBe([
        'id' => $image->id,
        'width' => 1200,
        'height' => 800,
        'description' => null,
        'url' => route('commit-image.show', $image),
    ]);
});

it('belongs to a commit', function (): void {
    $commit = Commit::factory()->create();
    $image = CommitImage::factory()->for($commit)->create();

    expect($image->commit->is($commit))->toBeTrue();
});

it('is deleted with its commit', function (): void {
    $image = CommitImage::factory()->create();

    $image->commit->forceDelete();

    expect(CommitImage::query()->count())->toBe(0);
});
