<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommitVideo;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use RuntimeException;

final readonly class CommitVideos
{
    /**
     * Only self-contained video containers are opened: playlists must never make a worker fetch URLs or local files.
     * Originals and metadata never become playback files. Limit both dimensions to Full HD and cap the output size.
     *
     * @return array{width: int, height: int, duration: float}
     */
    public function encode(string $directory): array
    {
        $probe = Process::timeout(30)->run([
            config()->string('video.ffprobe'), '-v', 'error', '-protocol_whitelist', 'file,pipe',
            '-format_whitelist', 'mov,matroska,webm', '-show_streams', '-show_format', '-of', 'json', $directory.'/source',
        ]);

        $metadata = json_decode($probe->output(), true);
        $metadata = new Fluent(is_array($metadata) ? $metadata : []);

        $stream = $metadata->collect('streams')->firstWhere('codec_type', 'video');
        $stream = new Fluent(is_array($stream) ? $stream : []);

        $duration = $metadata->float('format.duration');
        $width = $stream->integer('width');
        $height = $stream->integer('height');

        throw_if(! $probe->successful() || $duration <= 0 || $duration > CommitVideo::MAX_SECONDS
            || $width < 2 || $height < 2 || $width > 8192 || $height > 8192 || $width * $height > 33554432
            || $stream->integer('disposition.attached_pic') !== 0, InvalidArgumentException::class, 'Choose a playable MP4, MOV or WebM video of up to 60 seconds.');

        $result = Process::timeout(210)->run([
            config()->string('video.ffmpeg'), '-nostdin', '-v', 'error', '-y',
            '-protocol_whitelist', 'file,pipe', '-format_whitelist', 'mov,matroska,webm', '-i', $directory.'/source',
            '-map', '0:v:0', '-map', '0:a:0?', '-map_metadata', '-1', '-map_metadata:s', '-1', '-map_chapters', '-1',
            '-vf', "scale=w='min(if(gte(iw,ih),1920,1080),iw)':h='min(if(gte(iw,ih),1080,1920),ih)':force_original_aspect_ratio=decrease:force_divisible_by=2,setsar=1",
            '-r', '30', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-maxrate', '8M', '-bufsize', '16M', '-c:a', 'aac', '-b:a', '128k', '-ac', '2',
            '-t', '60', '-fs', '70000000', '-movflags', '+faststart', $directory.'/video.mp4',
        ]);

        throw_unless($result->successful(), RuntimeException::class, 'Video encoding failed.');

        $poster = Process::timeout(30)->run([
            config()->string('video.ffmpeg'), '-nostdin', '-v', 'error', '-y', '-i', $directory.'/video.mp4',
            '-frames:v', '1', '-map_metadata', '-1', $directory.'/poster.jpg',
        ]);

        throw_unless($poster->successful(), RuntimeException::class, 'Video poster creation failed.');

        $dimensions = getimagesize($directory.'/poster.jpg');

        throw_if($dimensions === false, RuntimeException::class, 'Video poster is unavailable.');

        return ['width' => $dimensions[0], 'height' => $dimensions[1], 'duration' => $duration];
    }
}
