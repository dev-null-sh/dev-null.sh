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

it('has a temporary url that stays the same for an hour at a time', function (): void {
    $this->travelTo(now()->setTime(9, 0));

    $image = CommitImage::factory()->create();
    $url = $image->temporaryUrl();

    expect($url)->toStartWith(route('commit-image.show', $image).'?expires='.now()->setTime(11, 0)->getTimestamp().'&signature=');

    $this->travelTo(now()->setTime(9, 59, 59));

    expect($image->temporaryUrl())->toBe($url);

    $this->travelTo(now()->setTime(10, 0));

    expect($image->temporaryUrl())->not->toBe($url);
});
