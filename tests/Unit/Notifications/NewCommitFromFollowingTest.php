<?php

declare(strict_types=1);

use App\Models\Commit;
use App\Models\User;
use App\Notifications\NewCommitFromFollowing;
use NotificationChannels\WebPush\WebPushChannel;

it('is sent to the database and web push', function (): void {
    $notification = new NewCommitFromFollowing(Commit::factory()->create());

    expect($notification->via(User::factory()->create()))->toBe(['database', WebPushChannel::class]);
});

it('stores the commit and its author', function (): void {
    $author = User::factory()->create(['username' => 'taylor', 'name' => 'Taylor', 'avatar_url' => null]);
    $commit = Commit::factory()->for($author)->create(['body' => str_repeat('a', 200)]);

    $data = new NewCommitFromFollowing($commit)->toArray(User::factory()->create());

    expect($data)->toBe([
        'commit_id' => $commit->id,
        'body' => str_repeat('a', 140).'...',
        'user' => [
            'username' => 'taylor',
            'name' => 'Taylor',
            'avatar_url' => null,
        ],
    ]);
});

it('builds a web push message linking to the notification', function (): void {
    $author = User::factory()->create(['username' => 'taylor']);
    $commit = Commit::factory()->for($author)->create(['body' => 'Hello, devnull!']);

    $notification = new NewCommitFromFollowing($commit);
    $notification->id = '0199e2a4-7c1b-7000-8000-000000000000';

    $message = $notification->toWebPush(User::factory()->create())->toArray();

    expect($message['title'])->toBe('@taylor committed')
        ->and($message['body'])->toBe('Hello, devnull!')
        ->and($message['data'])->toBe(['url' => route('notification.show', '0199e2a4-7c1b-7000-8000-000000000000')]);
});
