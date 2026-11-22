<?php

declare(strict_types=1);

use App\Actions\DeleteCommitVideo;
use App\Enums\CommitVideoStatus;
use App\Jobs\ProcessCommitVideo;
use App\Models\CommitVideo;
use App\Models\User;
use App\Services\CommitVideos;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake();
});

it('encodes a real upload for playback, strips its metadata and deletes the original', function (): void {
    Process::preventStrayProcesses(false);
    $source = (string) file_get_contents(__DIR__.'/../../Fixtures/commit-video.mp4');
    $video = CommitVideo::query()->create(['user_id' => User::factory()->create()->id, 'size' => mb_strlen($source, '8bit'), 'status' => CommitVideoStatus::Processing]);
    Storage::put($video->path('source'), $source);

    $job = new ProcessCommitVideo($video->id);
    dispatch_sync(new ProcessCommitVideo($video->id));
    expect($video->refresh()->status)->toBe(CommitVideoStatus::Ready)->and($video->width)->toBe(96)->and($video->height)->toBe(64)
        ->and($video->duration)->toBe(1.0);
    Storage::assertMissing($video->path('source'));
    Storage::assertExists([$video->path('video.mp4'), $video->path('poster.jpg')]);
    expect(Storage::get($video->path('video.mp4')))->not->toContain('private source title');
    $job->handle(resolve(CommitVideos::class));
    $job->failed();

    expect($video->refresh()->status)->toBe(CommitVideoStatus::Ready);
});

it('rejects a corrupt upload and removes all its files', function (): void {
    Process::preventStrayProcesses(false);
    $video = CommitVideo::query()->create(['user_id' => User::factory()->create()->id, 'size' => 4, 'status' => CommitVideoStatus::Processing]);
    Storage::put($video->path('source'), 'junk');
    new ProcessCommitVideo($video->id)->handle(resolve(CommitVideos::class));
    expect($video->refresh()->status)->toBe(CommitVideoStatus::Failed);
    Storage::assertMissing($video->path('source'));
});

it('rejects missing bytes and ignores cancelled jobs', function (): void {
    $video = CommitVideo::query()->create(['user_id' => User::factory()->create()->id, 'size' => 10, 'status' => CommitVideoStatus::Processing]);
    Storage::put($video->path('source'), 'short');
    $job = new ProcessCommitVideo($video->id);
    $job->handle(resolve(CommitVideos::class));

    expect($video->refresh()->status)->toBe(CommitVideoStatus::Failed);
    $video->delete();
    $job->handle(resolve(CommitVideos::class));
    $job->failed();

    expect(CommitVideo::query()->count())->toBe(0);
});

it('rejects videos over the duration limit rather than publishing a clipped upload', function (): void {
    $directory = storage_path('app/video-test-'.Str::uuid());
    File::ensureDirectoryExists($directory);
    Process::fake(['*' => Process::result(output: json_encode(['streams' => [['codec_type' => 'video', 'width' => 96, 'height' => 64]], 'format' => ['duration' => 60.01]], JSON_THROW_ON_ERROR))]);

    try {
        expect(fn (): array => resolve(CommitVideos::class)->encode($directory))->toThrow(InvalidArgumentException::class);
    } finally {
        File::deleteDirectory($directory);
    }
});

it('retains the source for retry but never exposes encoder errors or partial output', function (): void {
    $video = CommitVideo::query()->create(['user_id' => User::factory()->create()->id, 'size' => 4, 'status' => CommitVideoStatus::Processing]);
    Storage::put($video->path('source'), 'data');
    Process::fake(fn (PendingProcess $process): ProcessResult => is_array($process->command) && $process->command[0] === config()->string('video.ffprobe')
        ? Process::result(output: json_encode(['streams' => [['codec_type' => 'video', 'width' => 96, 'height' => 64]], 'format' => ['duration' => 1]], JSON_THROW_ON_ERROR))
        : Process::result(errorOutput: 'private metadata and decoder details', exitCode: 1));
    $job = new ProcessCommitVideo($video->id);
    expect(fn () => $job->handle(resolve(CommitVideos::class)))->toThrow(RuntimeException::class, 'Video processing failed for '.$video->id);
    expect($video->refresh()->status)->toBe(CommitVideoStatus::Processing)->and($video->toArray()['url'])->toBeNull();
    Storage::assertExists($video->path('source'));
    Storage::assertMissing($video->path('video.mp4'));
    $job->failed();
    expect($video->refresh()->status)->toBe(CommitVideoStatus::Failed);
    Storage::assertMissing($video->path('source'));
});

it('does not publish files when the upload is cancelled during encoding', function (): void {
    $video = CommitVideo::query()->create(['user_id' => User::factory()->create()->id, 'size' => 4, 'status' => CommitVideoStatus::Processing]);
    Storage::put($video->path('source'), 'data');
    Process::fake(function (PendingProcess $process) use ($video): ProcessResult {
        $command = $process->command;
        assert(is_array($command));

        if ($command[0] === config()->string('video.ffprobe')) {
            return Process::result(output: json_encode(['streams' => [['codec_type' => 'video', 'width' => 96, 'height' => 64]], 'format' => ['duration' => 1]], JSON_THROW_ON_ERROR));
        }

        $file = array_last($command);
        assert(is_string($file));
        File::put($file, str_ends_with($file, '.jpg') ? (string) UploadedFile::fake()->image('poster.jpg', 96, 64)->getContent() : 'encoded');
        resolve(DeleteCommitVideo::class)->handle($video);

        return Process::result();
    });
    new ProcessCommitVideo($video->id)->handle(resolve(CommitVideos::class));
    expect(CommitVideo::query()->count())->toBe(0);
    Storage::assertMissing([$video->path('source'), $video->path('video.mp4'), $video->path('poster.jpg')]);
});
