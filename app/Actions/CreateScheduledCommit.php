<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\NewPoll;
use App\Enums\CommitSource;
use App\Enums\UserUpdate;
use App\Events\UsersUpdated;
use App\Models\Organization;
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
    public function handle(User $user, string $body, CarbonImmutable $scheduledAt, array $images = [], array $imageDescriptions = [], ?NewPoll $poll = null, bool|string $linkPreview = true, ?Organization $organization = null, ?CommitSource $source = null): ScheduledCommit
    {
        $body = mb_trim($body);

        return DB::transaction(function () use ($user, $organization, $body, $poll, $images, $imageDescriptions, $linkPreview, $scheduledAt, $source): ScheduledCommit {
            $scheduled = ScheduledCommit::query()->create([
                'user_id' => $user->id,
                'author_id' => $organization?->id,
                'source' => $source,
                'body' => $body,
                'poll' => $poll instanceof NewPoll ? ['options' => $poll->options, 'duration' => $poll->duration->value] : null,
                'images' => $images === [] ? null : $this->createImageFiles->handle($images, $imageDescriptions),
                'link_preview_url' => match ($linkPreview) {
                    true => $this->urls->first($body),
                    false => null,
                    default => $linkPreview,
                },
                'scheduled_at' => $scheduledAt->utc(),
            ]);

            event(new UsersUpdated([$user->id], UserUpdate::ScheduledCommits));

            return $scheduled;
        });
    }
}
