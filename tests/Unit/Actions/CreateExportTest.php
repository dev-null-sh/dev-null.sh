<?php

declare(strict_types=1);

use App\Actions\CreateExport;
use App\Enums\CommitVideoStatus;
use App\Models\Commit;
use App\Models\CommitVideo;
use App\Models\User;

it('exports owned attached and unattached video metadata without storage paths or secrets', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->create();
    $attached = CommitVideo::query()->create(['user_id' => $user->id, 'commit_id' => $commit->id, 'status' => CommitVideoStatus::Ready, 'size' => 123, 'description' => 'A demo', 'width' => 640, 'height' => 480, 'duration' => 3.5]);
    $pending = CommitVideo::query()->create(['user_id' => $user->id, 'size' => 99, 'status' => CommitVideoStatus::Uploading]);
    CommitVideo::query()->create(['user_id' => User::factory()->create()->id, 'size' => 99]);

    $videos = collect(iterator_to_array(resolve(CreateExport::class)->handle($user)['videos']))->keyBy('id');

    expect($videos)->toHaveCount(2)
        ->and($videos[$attached->id])->toMatchArray(['commit_id' => $commit->id, 'size' => 123, 'description' => 'A demo', 'width' => 640, 'height' => 480, 'duration' => 3.5, 'status' => 'ready', 'url' => route('commit-video-playback.show', $attached)])
        ->and($videos[$pending->id]['commit_id'])->toBeNull()
        ->and($videos[$pending->id]['url'])->toBeNull()
        ->and(json_encode($videos, JSON_UNESCAPED_SLASHES))->not->toContain('video-uploads/', 'video.mp4', 'user_id', 'signature=');
});
