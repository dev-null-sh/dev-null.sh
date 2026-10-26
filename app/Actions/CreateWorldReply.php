<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Enums\ProfileType;
use App\Models\Commit;
use App\Models\Profile;
use App\Models\User;
use App\Services\HelloWorld;
use Illuminate\Support\Facades\DB;

final readonly class CreateWorldReply
{
    public const string GITHUB_ID = '0';

    public const string FALLBACK_HANDLE = 'devnull-world';

    private const string HANDLE = 'world';

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
        if (! $this->helloWorld->matches($commit->body)) {
            return;
        }

        DB::transaction(function () use ($commit): void {
            $commit->refresh();

            if ($commit->status !== CommitStatus::Approved) {
                return;
            }

            $author = $commit->user;

            if (! $author instanceof User) {
                return;
            }

            $world = $this->world();

            if (! $world instanceof User || $author->is($world)) {
                return;
            }

            if ($commit->replies()->whereBelongsTo($world)->exists()) {
                return;
            }

            $reply = $world->commits()->create([
                'body' => "Hello, {$author->name}.",
                'parent_id' => $commit->id,
                'root_id' => $commit->threadRootId(),
                'status' => CommitStatus::Approved,
            ]);

            $this->createReplyNotifications->handle($reply);
        });
    }

    /**
     * A real GitHub account named world keeps the name; the bot continues under its reserved handle.
     */
    private function world(): ?User
    {
        $world = User::query()->firstWhere('github_id', self::GITHUB_ID);

        if ($world instanceof User) {
            return in_array($world->handle, [self::HANDLE, self::FALLBACK_HANDLE], true) ? $world : null;
        }

        if (Profile::query()->where('handle', self::HANDLE)->exists()) {
            return null;
        }

        return DB::transaction(function (): User {
            Profile::query()->create([
                'type' => ProfileType::User,
                'github_id' => self::GITHUB_ID,
                'handle' => self::HANDLE,
                'name' => self::HANDLE,
                'bio' => 'I answer to hello world.',
            ]);

            return User::query()->create(['github_id' => self::GITHUB_ID, 'onboarded_at' => now()]);
        });
    }
}
