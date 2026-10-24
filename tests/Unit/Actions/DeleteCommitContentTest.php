<?php

declare(strict_types=1);

use App\Actions\CreateCommitRestoration;
use App\Actions\DeleteCommit;
use App\Actions\DeleteCommitContent;
use App\Models\Commit;
use App\Models\CommitImage;
use App\Models\LinkPreview;
use App\Models\Poll;
use App\Models\Topic;
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
    $commit = Commit::factory()->create(['body' => 'Hello @taylor', 'mentions' => ['taylor'], 'link_preview_id' => LinkPreview::factory()]);
    $poll = Poll::factory()->withOptions('Yes', 'No')->create(['commit_id' => $commit->id]);
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel(30)->seconds();

    resolve(DeleteCommitContent::class)->handle($commit);

    $commit = Commit::withTrashed()->findOrFail($commit->id);
    expect($commit)
        ->body->toBe('')
        ->mentions->toBeNull()
        ->link_preview_id->toBeNull()
        ->user_id->not->toBeNull();
    $this->assertModelMissing($poll);
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

it('erases topic associations but retains orphan labels and associations of restorable commits', function (): void {
    $this->freezeTime();
    $commit = Commit::factory()->create();
    $other = Commit::factory()->create();
    $sole = Topic::factory()->create(['last_applied_at' => now()->subDay()]);
    $shared = Topic::factory()->create(['last_applied_at' => now()->subDay()]);
    $unrelated = Topic::factory()->create();
    $commit->topics()->attach([$sole->id, $shared->id]);
    $other->topics()->attach($shared);
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel(30)->seconds();
    resolve(DeleteCommit::class)->handle($other);

    resolve(DeleteCommitContent::class)->handle($commit);

    $this->assertDatabaseMissing('commit_topic', ['commit_id' => $commit->id]);
    $this->assertModelExists($sole);
    $this->assertModelExists($shared);
    $this->assertModelExists($unrelated);
    expect($other->topics()->pluck('topics.id')->all())->toBe([$shared->id]);
    expect($sole->fresh()->last_applied_at->toDateTimeString())->toBe($sole->last_applied_at->toDateTimeString());
    expect($shared->fresh()->last_applied_at->toDateTimeString())->toBe($shared->last_applied_at->toDateTimeString());
});

it('retains topic labels and associations throughout the undo window', function (): void {
    $commit = Commit::factory()->create();
    $topic = Topic::factory()->create();
    $commit->topics()->attach($topic);
    resolve(DeleteCommit::class)->handle($commit);
    $this->travel(29)->seconds();

    resolve(DeleteCommitContent::class)->handle($commit);

    expect($commit->topics()->pluck('topics.id')->all())->toBe([$topic->id]);
});
