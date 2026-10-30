<?php

declare(strict_types=1);

use App\Actions\CreateCommitRestoration;
use App\Actions\CreateCommitTopics;
use App\Actions\DeleteCommit;
use App\Actions\DeleteCommitContent;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\LinkPreview;
use App\Models\Organization;
use App\Models\Poll;
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

    resolve(DeleteCommitContent::class)->handle($image->commit);

    $this->assertModelMissing($image);
    Storage::disk()->assertMissing($image->path);
});

it('keeps the images while the commit can still be restored', function (): void {
    $image = deletedCommitWithImage();
    $this->travel(29)->seconds();

    resolve(DeleteCommitContent::class)->handle($image->commit);

    $this->assertModelExists($image);
    Storage::disk()->assertExists($image->path);
});

it('keeps the images of a restored commit', function (): void {
    $image = deletedCommitWithImage();
    resolve(CreateCommitRestoration::class)->handle($image->commit);
    $this->travel(30)->seconds();

    resolve(DeleteCommitContent::class)->handle($image->commit);

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

    resolve(DeleteCommitContent::class)->handle($commit);

    $this->assertModelExists($image);
    Storage::disk()->assertExists($image->path);
});

it('erases the content once the undo window has closed', function (): void {
    $commit = Commit::factory()->create(['body' => 'Hello @taylor and @laravel', 'mentions' => ['taylor'], 'link_preview_id' => LinkPreview::factory()]);
    resolve(CreateCommitTopics::class)->handle($commit, ['laravel' => Organization::factory()->create()->id]);
    $poll = Poll::factory()->withOptions('Yes', 'No')->create(['commit_id' => $commit->id]);
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel(30)->seconds();

    resolve(DeleteCommitContent::class)->handle($commit);

    $commit = Commit::withTrashed()->findOrFail($commit->id);
    expect($commit)
        ->body->toBe('')
        ->mentions->toBeNull()
        ->organization_mentions->toBeNull()
        ->link_preview_id->toBeNull()
        ->user_id->not->toBeNull();
    $this->assertModelMissing($poll);
    $this->assertDatabaseMissing('commit_topics', ['commit_id' => $commit->id]);
});

it('erases the previews of links to the commit, which hold its body', function (): void {
    $commit = Commit::factory()->create();
    $preview = LinkPreview::factory()->create(['url' => route('commit.show', [$commit->user, $commit]), 'description' => $commit->body]);
    $linking = Commit::factory()->create(['link_preview_id' => $preview->id]);
    $other = LinkPreview::factory()->create(['url' => 'https://example.com/commits/'.Commit::factory()->create()->id]);
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel(30)->seconds();

    resolve(DeleteCommitContent::class)->handle($commit);

    $this->assertModelMissing($preview);
    $this->assertModelExists($other);
    expect($linking->fresh()->link_preview_id)->toBeNull();
});

it('keeps the content while the commit can still be restored', function (): void {
    $commit = Commit::factory()->create(['body' => 'Hello @taylor', 'mentions' => ['taylor']]);
    $poll = Poll::factory()->create(['commit_id' => $commit->id]);
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel(29)->seconds();

    resolve(DeleteCommitContent::class)->handle($commit);

    expect(Commit::withTrashed()->findOrFail($commit->id))
        ->body->toBe('Hello @taylor')
        ->mentions->toBe(['taylor']);
    $this->assertModelExists($poll);
});

it('does nothing for a commit that is gone', function (): void {
    $commit = Commit::factory()->create();
    $commit->forceDelete();

    resolve(DeleteCommitContent::class)->handle($commit);

    expect(CommitImage::query()->count())->toBe(0);
});
