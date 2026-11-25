<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CommitVideo;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Fluent;

final readonly class CreateCommitVideo
{
    /** @return array{video: CommitVideo, upload: array{url: string, method: string, headers: array<mixed>}} */
    public function handle(User $user, int $size): array
    {
        $video = CommitVideo::query()->create(['user_id' => $user->id, 'size' => $size]);
        $upload = config('filesystems.disks.'.config()->string('filesystems.default').'.driver') === 'local'
            ? ['url' => route('commit-video-upload.update', $video), 'headers' => ['Content-Type' => 'application/octet-stream']]
            : Storage::temporaryUploadUrl($video->path('source'), now()->addMinutes(15), ['ContentType' => 'application/octet-stream', 'ContentLength' => $size]);

        $upload = new Fluent($upload);

        return ['video' => $video->refresh(), 'upload' => ['url' => $upload->string('url')->value(), 'method' => 'PUT', 'headers' => Arr::except($upload->array('headers'), ['Host', 'Content-Length'])]];
    }
}
