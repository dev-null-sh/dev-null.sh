<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Actions\CreateTomFriendship;
use App\Models\Commit;
use App\Models\User;
use App\Queries\CommitAncestorsQuery;
use Illuminate\Support\Collection;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\Data\ToolResult;

#[Provider('opencode')]
#[Model('deepseek-v4.1-flash')]
#[MaxTokens(700)]
#[Timeout(15)]
final class Tom implements Agent, Conversational, HasProviderOptions
{
    use Promptable;

    /** @param list<ToolResult> $evidence */
    public function __construct(private Commit $commit, private array $evidence = [])
    {
        //
    }

    public function instructions(): string
    {
        $messages = $this->messages();
        $characters = mb_strlen($this->commit->body) + $messages->sum(fn (Message $message): int => mb_strlen($message->content ?? ''));

        return mb_trim(view('ai.agents.tom', [
            'botMention' => User::query()->where('github_id', CreateTomFriendship::GITHUB_ID)->first(['username'])?->mention() ?? '@'.CreateTomFriendship::USERNAME,
            'mention' => $this->commit->user?->mention() ?? '@',
            'nearlyFull' => $characters >= 9600 || $messages->count() >= 10,
            'evidence' => collect($this->evidence)->map(fn (ToolResult $result): array => [
                'tool' => $result->name,
                'result' => $result->result,
            ])->toJson(),
        ])->render());
    }

    /**
     * @return Collection<int, Message>
     */
    public function messages(): Collection
    {
        $remaining = 12000 - mb_strlen($this->commit->body);

        return resolve(CommitAncestorsQuery::class)->get($this->commit, null, limit: 12)
            ->reject(fn (Commit $commit): bool => $commit->trashed() || $commit->user === null)
            ->map(fn (Commit $commit): Message => new Message(
                $commit->user?->github_id === CreateTomFriendship::GITHUB_ID ? 'assistant' : 'user',
                $commit->user?->mention().': '.$commit->body,
            ))
            ->reverse()
            ->takeWhile(function (Message $message) use (&$remaining): bool {
                $remaining -= mb_strlen($message->content ?? '');

                return $remaining >= 0;
            })
            ->reverse()
            ->values();
    }

    /** @return array<string, mixed> */
    public function providerOptions(Lab|string $provider): array
    {
        return ['thinking' => ['type' => 'disabled']];
    }
}
