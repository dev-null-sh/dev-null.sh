<?php

declare(strict_types=1);

use App\Actions\DeleteCommit;
use App\Models\Commit;
use App\Models\CommitImage;
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

it('erases the images of a deleted commit', function (): void {
    Storage::fake();

    $commit = Commit::factory()->create();
    $image = CommitImage::factory()->for($commit)->create();
    Storage::disk()->put($image->path, 'image');

    resolve(DeleteCommit::class)->handle($commit);

    expect(CommitImage::query()->count())->toBe(0);

    Storage::disk()->assertMissing($image->path);
});
