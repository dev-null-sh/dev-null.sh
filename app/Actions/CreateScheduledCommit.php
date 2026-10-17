<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\NewPoll;
use App\Models\ScheduledCommit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

final readonly class CreateScheduledCommit
{
    public function __construct(private CreateCommitImageFiles $createCommitImageFiles)
    {
        //
    }

    /**
     * Images are processed and stored now, but stay private until they are attached to the commit at the due time.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $imageDescriptions
     */
    public function handle(User $user, string $body, CarbonImmutable $scheduledAt, array $images = [], array $imageDescriptions = [], ?NewPoll $poll = null): ScheduledCommit
    {
        return ScheduledCommit::query()->create([
            'user_id' => $user->id,
            'body' => mb_trim($body),
            'poll' => $poll instanceof NewPoll ? ['options' => $poll->options, 'duration' => $poll->duration->value] : null,
            'images' => $images === [] ? null : $this->createCommitImageFiles->handle($images, $imageDescriptions),
            'scheduled_at' => $scheduledAt->utc(),
        ]);
    }
}
