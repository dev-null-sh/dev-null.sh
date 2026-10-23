<?php

declare(strict_types=1);

use App\Actions\DeleteCommitVideo;
use App\Enums\CommitVideoStatus;
use App\Jobs\PruneCommitVideos;
use App\Models\Commit;
use App\Models\CommitVideo;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

it('prunes abandoned videos but retains recent uploads and attached videos', function (): void {
    Storage::fake();
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    $old = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 10, 'created_at' => now()->subHours(25)]);
    $recent = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 10, 'created_at' => now()->subMinutes(59)]);
    $attached = CommitVideo::query()->create(['user_id' => $user->id, 'commit_id' => $commit->id, 'size' => 10, 'created_at' => now()->subDays(2)]);
    foreach ([$old, $recent, $attached] as $video) {
        foreach (['source', 'video.mp4', 'poster.jpg'] as $file) {
            Storage::put($video->path($file), 'video');
        }
    }

    resolve(PruneCommitVideos::class)->handle(resolve(DeleteCommitVideo::class));

    $this->assertModelMissing($old);
    Storage::assertMissing([$old->path('source'), $old->path('video.mp4'), $old->path('poster.jpg')]);
    $this->assertModelExists($recent);
    $this->assertModelExists($attached);
    Storage::assertExists([$recent->path('source'), $attached->path('video.mp4')]);
});

it('prunes unfinished uploads after an hour but keeps ready drafts for a day', function (): void {
    Storage::fake();
    $user = User::factory()->create();
    $stale = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 10, 'created_at' => now()->subMinutes(61)]);
    $fresh = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 10, 'created_at' => now()->subMinutes(59)]);
    $ready = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 10, 'status' => CommitVideoStatus::Ready, 'created_at' => now()->subHours(23)]);
    $processing = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 10, 'status' => CommitVideoStatus::Processing, 'created_at' => now()->subHours(2)]);
    Storage::put($stale->path('source'), str_repeat('x', 5000));

    resolve(PruneCommitVideos::class)->handle(resolve(DeleteCommitVideo::class));

    $this->assertModelMissing($stale);
    Storage::assertMissing($stale->path('source'));
    $this->assertModelExists($fresh);
    $this->assertModelExists($ready);
    $this->assertModelExists($processing);
});

it('prunes late orphaned uploads after an hour and scratch directories after 24 hours', function (): void {
    Storage::fake();
    Storage::put('video-uploads/cancelled', 'late PUT');
    Storage::put('video-uploads/recent', 'new PUT');
    touch(Storage::path('video-uploads/cancelled'), now()->subMinutes(61)->getTimestamp());
    touch(Storage::path('video-uploads/recent'), now()->subMinutes(59)->getTimestamp());
    $queued = CommitVideo::query()->create(['user_id' => User::factory()->create()->id, 'size' => 7, 'status' => CommitVideoStatus::Processing]);
    Storage::put($queued->path('source'), 'waiting');
    touch(Storage::path($queued->path('source')), now()->subHours(2)->getTimestamp());
    $scratch = storage_path('app/video-processing/'.Str::uuid());
    $recent = storage_path('app/video-processing/'.Str::uuid());
    File::ensureDirectoryExists($scratch);
    File::ensureDirectoryExists($recent);
    File::put($scratch.'/source', 'private original');
    touch($scratch, now()->subHours(25)->getTimestamp());

    try {
        resolve(PruneCommitVideos::class)->handle(resolve(DeleteCommitVideo::class));

        Storage::assertMissing('video-uploads/cancelled');
        Storage::assertExists('video-uploads/recent');
        Storage::assertExists($queued->path('source'));
        expect(File::isDirectory($scratch))->toBeFalse()
            ->and(File::isDirectory($recent))->toBeTrue();
    } finally {
        File::deleteDirectory($scratch);
        File::deleteDirectory($recent);
    }
});
