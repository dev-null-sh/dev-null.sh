<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Jobs\EraseDeletedCommitImages;
use App\Models\Commit;
use App\Models\CommitImage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('may delete a commit', function (): void {
    $commit = Commit::factory()->create();

    $action = resolve(DeleteCommit::class);

    $action->handle($commit);

    $this->assertSoftDeleted($commit);
});

it('keeps the replies of a deleted commit', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();

    resolve(DeleteCommit::class)->handle($commit);

    $this->assertNotSoftDeleted($reply);
});

it('keeps the quotes of a deleted commit', function (): void {
    $commit = Commit::factory()->create();
    $quote = Commit::factory()->for($commit, 'quotedCommit')->create();

    resolve(DeleteCommit::class)->handle($commit);

    $this->assertNotSoftDeleted($quote);
});

it('keeps the images of a deleted commit until the undo window closes', function (): void {
    Storage::fake();
    Queue::fake();

    $commit = Commit::factory()->create();
    $image = CommitImage::factory()->for($commit)->create();
    Storage::disk()->put($image->path, 'image');

    resolve(DeleteCommit::class)->handle($commit);

    $this->assertModelExists($image);
    Storage::disk()->assertExists($image->path);

    Queue::assertPushed(
        EraseDeletedCommitImages::class,
        fn (EraseDeletedCommitImages $job): bool => $job->commit->is($commit)
            && $job->delay instanceof DateTimeInterface
            && now()->addSeconds(30)->equalTo($job->delay),
    );
});

it('does not queue the erasure again for a commit already deleted', function (): void {
    $commit = Commit::factory()->create();
    resolve(DeleteCommit::class)->handle($commit);
    Queue::fake();

    resolve(DeleteCommit::class)->handle($commit);

    Queue::assertNothingPushed();
});
