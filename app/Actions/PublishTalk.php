<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Talk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class PublishTalk
{
    public function __construct(private CreateCommit $createCommit)
    {
        //
    }

    public function handle(Talk $talk, bool $unlisted = false): Talk
    {
        if ($talk->isPublished()) {
            $talk->forceFill(['is_unlisted' => $unlisted])->save();

            return $talk->refresh();
        }

        if ($talk->slides_url === null && $talk->recording_url === null) {
            throw new InvalidArgumentException('A talk needs a slides URL, a recording URL, or both before it can be published.');
        }

        return DB::transaction(function () use ($talk, $unlisted): Talk {
            $commit = $this->createCommit->handle(
                $talk->user,
                $this->commitBody($talk),
                linkPreview: $talk->recording_url ?? $talk->slides_url,
            );

            $talk->forceFill([
                'commit_id' => $commit->id,
                'published_at' => now(),
                'is_unlisted' => $unlisted,
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
}
