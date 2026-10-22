<?php

declare(strict_types=1);

namespace App\Actions;

use App\Dto\NewEvent;
use App\Models\Event;
use App\Models\User;
use App\Services\EmojiShortcodes;
use Illuminate\Support\Facades\DB;

final readonly class CreateEvent
{
    public function __construct(private CreateCommit $createCommit, private EmojiShortcodes $emojiShortcodes)
    {
        //
    }

    /**
     * An event is announced by a commit, whose body is its description: the event goes through moderation with it, is
     * discussed in its replies, and is deleted along with it.
     */
    public function handle(User $user, string $body, NewEvent $newEvent): Event
    {
        return DB::transaction(function () use ($user, $body, $newEvent): Event {
            $commit = $this->createCommit->handle($user, $body);

            $event = $commit->event()->create([
                'title' => $this->emojiShortcodes->replace($newEvent->title),
                'starts_at' => $newEvent->startsAt,
                'ends_at' => $newEvent->endsAt,
                'location' => $newEvent->location,
                'url' => $newEvent->url,
            ]);

            $event->agendaItems()->createMany(array_map(fn (array $item): array => [
                'label' => $this->emojiShortcodes->replace($item['label']),
                'starts_at' => $item['starts_at'],
            ], $newEvent->agenda));

            return $event->setRelation('commit', $commit);
        });
    }
}
