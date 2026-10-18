<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Talk;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class CreateTalk
{
    public function __construct(private CreateCommit $createCommit)
    {
        //
    }

    public function handle(
        User $user,
        string $title,
        string $description,
        CarbonInterface $presentedOn,
        ?string $slidesUrl = null,
        ?string $recordingUrl = null,
        ?string $eventName = null,
    ): Talk {
        $title = mb_trim($title);
        $description = mb_trim($description);
        $slidesUrl = $this->normalizeUrl($slidesUrl);
        $recordingUrl = $this->normalizeUrl($recordingUrl);
        $eventName = $eventName === null ? null : mb_trim($eventName);

        if ($slidesUrl === null && $recordingUrl === null) {
            throw new InvalidArgumentException('A talk needs a slides URL, a recording URL, or both.');
        }

        return DB::transaction(function () use ($user, $title, $description, $presentedOn, $slidesUrl, $recordingUrl, $eventName): Talk {
            $talk = Talk::query()->create([
                'user_id' => $user->id,
                'title' => $title,
                'description' => $description,
                'presented_on' => $presentedOn->toDateString(),
                'slides_url' => $slidesUrl,
                'recording_url' => $recordingUrl,
                'event_name' => $eventName === '' ? null : $eventName,
            ]);

            $commit = $this->createCommit->handle(
                $user,
                $this->commitBody($talk),
                linkPreview: $talk->recording_url ?? $talk->slides_url,
            );

            $talk->forceFill([
                'commit_id' => $commit->id,
                'published_at' => now(),
            ])->save();

            return $talk->refresh();
        });
    }

    private function commitBody(Talk $talk): string
    {
        $lines = [
            "I gave a talk: {$talk->title}",
        ];

        if (is_string($talk->event_name) && $talk->event_name !== '') {
            $lines[] = "at {$talk->event_name}";
        }

        $lines[] = '';
        $lines[] = Str::limit($talk->description, 300);
        $lines[] = '';

        if (is_string($talk->recording_url)) {
            $lines[] = $talk->recording_url;
        }

        if (is_string($talk->slides_url)) {
            $lines[] = $talk->slides_url;
        }

        return implode("\n", $lines);
    }

    private function normalizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = mb_trim($url);

        return $url === '' ? null : $url;
    }
}
