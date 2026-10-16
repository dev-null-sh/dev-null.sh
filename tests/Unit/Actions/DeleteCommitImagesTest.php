<?php

declare(strict_types=1);

use App\Actions\CreateCommitRestoration;
use App\Actions\DeleteCommit;
use App\Actions\DeleteCommitImages;
use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
    Queue::fake();
});

function deletedCommitWithImage(): CommitImage
{
    $image = CommitImage::factory()->create();
    Storage::disk()->put($image->path, 'image');

    resolve(DeleteCommit::class)->handle($image->commit);

    return $image;
}

it('erases the images once the undo window has closed', function (): void {
    $image = deletedCommitWithImage();
    $this->travel(30)->seconds();

    resolve(DeleteCommitImages::class)->handle($image->commit);

    $this->assertModelMissing($image);
    Storage::disk()->assertMissing($image->path);
});

it('keeps the images while the commit can still be restored', function (): void {
    $image = deletedCommitWithImage();
    $this->travel(29)->seconds();

    resolve(DeleteCommitImages::class)->handle($image->commit);

    $this->assertModelExists($image);
    Storage::disk()->assertExists($image->path);
});

it('keeps the images of a restored commit', function (): void {
    $image = deletedCommitWithImage();
    resolve(CreateCommitRestoration::class)->handle($image->commit);
    $this->travel(30)->seconds();

    resolve(DeleteCommitImages::class)->handle($image->commit);

    $this->assertModelExists($image);
    Storage::disk()->assertExists($image->path);
});

it('keeps the images of a commit deleted again within its new undo window', function (): void {
    $image = deletedCommitWithImage();
    $commit = $image->commit;
    resolve(CreateCommitRestoration::class)->handle($commit);
    $this->travel(20)->seconds();
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel(10)->seconds();

    resolve(DeleteCommitImages::class)->handle($commit);

    $this->assertModelExists($image);
    Storage::disk()->assertExists($image->path);
});

it('does nothing for a commit that is gone', function (): void {
    $commit = Commit::factory()->create();
    $commit->forceDelete();

    resolve(DeleteCommitImages::class)->handle($commit);

    expect(CommitImage::query()->count())->toBe(0);
});
