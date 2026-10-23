<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CommitVideoStatus;
use App\Models\CommitVideo;
use App\Services\CommitVideos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

#[Backoff(60)]
#[Timeout(300)]
#[Tries(3)]
final class ProcessCommitVideo implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $videoId)
    {
        $this->onQueue('videos');
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->videoId)->releaseAfter(60)->expireAfter(360)];
    }

    public function handle(CommitVideos $videos): void
    {
        $video = CommitVideo::query()->find($this->videoId);

        if ($video === null || $video->status !== CommitVideoStatus::Processing) {
            return;
        }

        $directory = storage_path('app/video-processing/'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);

        try {
            throw_if(Storage::size($video->path('source')) !== $video->size || $video->size > CommitVideo::MAX_BYTES, InvalidArgumentException::class, 'Video upload size does not match.');

            $source = Storage::readStream($video->path('source'));
            $target = fopen($directory.'/source', 'wb');

            throw_if(! is_resource($source) || $target === false, RuntimeException::class, 'Video upload is unavailable.');

            try {
                $bytes = stream_copy_to_stream($source, $target, CommitVideo::MAX_BYTES + 1);
            } finally {
                fclose($source);
                fclose($target);
            }

            throw_if($bytes !== $video->size, InvalidArgumentException::class, 'Video upload size does not match.');

            $metadata = $videos->encode($directory);

            // Cancellation and account deletion may happen while encoding. Only a locked, still-pending row can publish files.
            DB::transaction(function () use ($video, $directory, $metadata): void {
                $current = CommitVideo::query()->lockForUpdate()->find($video->id);

                if ($current === null || $current->status !== CommitVideoStatus::Processing) {
                    return;
                }

                foreach (['video.mp4', 'poster.jpg'] as $file) {
                    $stream = fopen($directory.'/'.$file, 'rb');
                    assert(is_resource($stream));

                    try {
                        throw_unless(Storage::put($video->path($file), $stream), RuntimeException::class, 'Video storage failed.');
                    } finally {
                        fclose($stream);
                    }
                }

                $current->update([...$metadata, 'status' => CommitVideoStatus::Ready]);
                Storage::delete($video->path('source'));
            });
        } catch (InvalidArgumentException) {
            $this->failed();
        } catch (Throwable) {
            // Decoder output and source metadata never go into failed-job payloads or logs.
            throw new RuntimeException('Video processing failed for '.$this->videoId);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        DB::transaction(function (): void {
            $video = CommitVideo::query()->where('status', CommitVideoStatus::Processing)->lockForUpdate()->find($this->videoId);

            if ($video !== null) {
                $video->update(['status' => CommitVideoStatus::Failed]);
                Storage::deleteDirectory('commit-videos/'.$video->id);
                Storage::delete($video->path('source'));
            }
        });
    }
}
