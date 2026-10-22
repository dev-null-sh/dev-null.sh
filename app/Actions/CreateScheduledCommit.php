<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\NewPoll;
use App\Models\ScheduledCommit;
use App\Models\User;
use App\Services\Urls;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class CreateScheduledCommit
{
    public function __construct(private CreateImageFiles $createImageFiles, private Urls $urls)
    {
        //
    }

    /**
     * Images are processed and stored now, but stay private until they are attached to the commit at the due time.
     * The link preview is of the first URL in the body when true, of the given URL when a string, or none when false.
     *
     * @param  list<UploadedFile>  $images
     * @param  array<int, string|null>  $imageDescriptions
     */
    public function handle(User $user, string $body, CarbonImmutable $scheduledAt, array $images = [], array $imageDescriptions = [], ?NewPoll $poll = null, bool|string $linkPreview = true): ScheduledCommit
    {
        $body = mb_trim($body);

        return DB::transaction(fn (): ScheduledCommit => ScheduledCommit::query()->create([
            'user_id' => $user->id,
            'body' => $body,
            'poll' => $poll instanceof NewPoll ? ['options' => $poll->options, 'duration' => $poll->duration->value] : null,
            'images' => $images === [] ? null : $this->createImageFiles->handle($images, $imageDescriptions),
            'link_preview_url' => match ($linkPreview) {
                true => $this->urls->first($body),
                false => null,
                default => $linkPreview,
            },
            'scheduled_at' => $scheduledAt->utc(),
        ]));
    }
}
