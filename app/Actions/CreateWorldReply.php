<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Services\HelloWorld;
use Illuminate\Support\Facades\DB;

final readonly class CreateWorldReply
{
    private const string GITHUB_ID = '0';

    private const string USERNAME = 'world';

    private const string REPLY = 'hello.';

    public function __construct(
        private HelloWorld $helloWorld,
        private CreateReplyNotifications $createReplyNotifications,
    ) {
        //
    }

    /**
     * @world answers an approved hello world, once. A pending one waits until it is approved.
     */
    public function handle(Commit $commit): void
    {
        if ($commit->status !== CommitStatus::Approved || ! $this->helloWorld->matches($commit->body)) {
            return;
        }

        DB::transaction(function () use ($commit): void {
            $world = $this->world();

            if (! $world instanceof User || $commit->user_id === $world->id) {
                return;
            }

            if ($commit->replies()->whereBelongsTo($world)->exists()) {
                return;
            }

            $reply = $world->commits()->create([
                'body' => self::REPLY,
                'parent_id' => $commit->id,
                'status' => CommitStatus::Approved,
            ]);

            $this->createReplyNotifications->handle($reply);
        });
    }

    /**
     * A real GitHub account named world keeps the name. The bot never signs in.
     */
    private function world(): ?User
    {
        $world = User::query()->firstWhere('github_id', self::GITHUB_ID);

        if ($world instanceof User) {
            return $world;
        }

        if (User::query()->where('username', self::USERNAME)->exists()) {
            return null;
        }

        return User::query()->create([
            'github_id' => self::GITHUB_ID,
            'username' => self::USERNAME,
            'name' => self::USERNAME,
            'bio' => 'I answer to hello world.',
            'onboarded_at' => now(),
        ]);
    }
}
