<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Actions\UpdateCommitHotScores;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Services\TheAlgorithm;
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

it('erases the images of a deleted commit', function (): void {
    Storage::fake();

    $commit = Commit::factory()->create();
    $image = CommitImage::factory()->for($commit)->create();
    Storage::disk()->put($image->path, 'image');

    resolve(DeleteCommit::class)->handle($commit);

    expect(CommitImage::query()->count())->toBe(0);

    Storage::disk()->assertMissing($image->path);
});

it('lowers the thread when a reply is deleted', function (): void {
    $commit = Commit::factory()->create();
    $reply = Commit::factory()->for($commit, 'parent')->create();
    resolve(UpdateCommitHotScores::class)->handle([$commit->id]);

    resolve(DeleteCommit::class)->handle($reply);

    expect($commit->fresh()->hot_score)
        ->toEqualWithDelta(resolve(TheAlgorithm::class)->score($commit->created_at, 0, 0), 1e-9);
});
