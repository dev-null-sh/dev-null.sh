<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\Tom;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use App\Queries\TomQuery;
use App\Services\TomToolDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\UserMessage;
use RuntimeException;

final readonly class CreateTomReply
{
    public function __construct(private CreateCommit $createCommit, private TomQuery $tomQuery, private TomToolDecision $toolDecision)
    {
        //
    }

    public function handle(string $commitId): void
    {
        if (blank(config('ai.providers.opencode.key')) || blank(config('ai.providers.openai.key'))) {
            return;
        }

        $commit = Commit::query()->visibleTo(null)->with('user')->find($commitId);

        if (! $commit instanceof Commit) {
            return;
        }

        $tom = $this->tomQuery->get();

        if (! $tom instanceof User || ! $commit->isAddressedTo($tom) || ! $this->replyable($commit, $tom) || $commit->user?->is($tom)) {
            return;
        }

        $history = new Tom($commit)->messages();
        $tools = $this->toolDecision->decide($commit->body, $history);
        $attempt = 0;
        $messages = [];
        $called = collect();

        do {
            $response = new Tom($commit, $tools, $messages)->prompt($commit->body);
            $messages = [...$messages, new UserMessage($commit->body), ...$response->messages];
            $called = $called->concat($response->toolResults->pluck('name'));
            $missingTools = $tools->contains(fn (Tool $tool): bool => $called->doesntContain(class_basename($tool)));
            $attempt++;
        } while ($missingTools && $attempt < 3);

        if ($missingTools) {
            throw new RuntimeException('Tom skipped a selected tool after retries.');
        }

        $body = mb_trim($response->text);

        if ($body === '') {
            throw new RuntimeException('Tom returned an empty reply.');
        }

        DB::transaction(function () use ($commit, $tom, $body): void {
            $current = Commit::query()->lockForUpdate()->find($commit->id);

            if (! $current instanceof Commit || $current->status !== CommitStatus::Approved
                || $current->body !== $commit->body || ! $this->replyable($current, $tom)) {
                return;
            }

            $this->createCommit->handle($tom, Str::limit($body, Commit::MAX_BODY_LENGTH - 3), parent: $current, linkPreview: false);
        });
    }

    /**
     * Tom replies once per post, and never to someone he has a block with.
     */
    private function replyable(Commit $commit, User $tom): bool
    {
        return $commit->user instanceof User
            && ! $commit->user->hasBlockWith($tom)
            && ! $commit->replies()->withTrashed()->whereBelongsTo($tom)->exists();
    }
}
