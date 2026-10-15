<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Commit;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

#[DeleteWhenMissingModels]
final class NewCommitFromFollowing extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Commit $commit)
    {
        //
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * @return array{commit_id: string, body: string, user: array{username: string, name: string, avatar_url: string|null}}
     */
    public function toArray(User $notifiable): array
    {
        return [
            'commit_id' => $this->commit->id,
            'body' => Str::limit($this->commit->body, 140),
            'user' => [
                'username' => $this->commit->user->username,
                'name' => $this->commit->user->name,
                'avatar_url' => $this->commit->user->avatar_url,
            ],
        ];
    }

    public function toWebPush(User $notifiable): WebPushMessage
    {
        return new WebPushMessage()
            ->title('@'.$this->commit->user->username.' committed')
            ->body(Str::limit($this->commit->body, 140))
            ->icon('/apple-touch-icon.png')
            ->tag('commit-'.$this->commit->id)
            // Goes through notification.show, so opening the push marks it read too.
            ->data(['url' => route('notification.show', $this->id)]);
    }
}
