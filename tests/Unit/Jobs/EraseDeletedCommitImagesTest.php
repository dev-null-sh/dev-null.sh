<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Jobs\EraseDeletedCommitImages;
use App\Models\CommitImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('erases the images of its deleted commit once the undo window has closed', function (): void {
    Storage::fake();

    $image = CommitImage::factory()->create();
    Storage::disk()->put($image->path, 'image');
    $job = serialize(new EraseDeletedCommitImages(resolve(DeleteCommit::class)->handle($image->commit)));
    $this->travel(30)->seconds();

    dispatch_sync(unserialize($job));

    $this->assertModelMissing($image);
    Storage::disk()->assertMissing($image->path);
});

it('is dropped when its commit is gone', function (): void {
    Queue::connection('database')->push(new EraseDeletedCommitImages(CommitImage::factory()->create()->commit));

    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);

    expect($payload)->deleteWhenMissingModels->toBeTrue();
});
