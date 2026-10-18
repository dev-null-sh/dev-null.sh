<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\TalkLinkKind;
use App\Models\Talk;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        string $linkUrl,
        TalkLinkKind $linkKind,
        ?string $eventName = null,
    ): Talk {
        $title = mb_trim($title);
        $description = mb_trim($description);
        $linkUrl = mb_trim($linkUrl);
        $eventName = $eventName === null ? null : mb_trim($eventName);

        return DB::transaction(function () use ($user, $title, $description, $presentedOn, $linkUrl, $linkKind, $eventName): Talk {
            $talk = Talk::query()->create([
                'user_id' => $user->id,
                'title' => $title,
                'description' => $description,
                'presented_on' => $presentedOn->toDateString(),
                'link_url' => $linkUrl,
                'link_kind' => $linkKind,
                'event_name' => $eventName === '' ? null : $eventName,
            ]);

            $commit = $this->createCommit->handle(
                $user,
                $this->commitBody($talk),
                linkPreview: $talk->link_url,
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
        $lines[] = $talk->link_url;

        return implode("\n", $lines);
    }
}
