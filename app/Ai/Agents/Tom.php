<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Actions\CreateTomFriendship;
use App\Ai\Tools\CommunityStats;
use App\Ai\Tools\RepositoryInsights;
use App\Ai\Tools\SearchCommits;
use App\Ai\Tools\SearchKnowledgeBase;
use App\Models\Commit;
use App\Queries\CommitAncestorsQuery;
use Illuminate\Support\Collection;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

#[Provider('opencode')]
#[Model('deepseek-v4.1-flash')]
#[MaxSteps(3)]
#[MaxTokens(700)]
#[Timeout(15)]
final class Tom implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    public function __construct(private Commit $commit)
    {
        //
    }

    public function instructions(): string
    {
        $messages = $this->messages();
        $characters = mb_strlen($this->commit->body) + $messages->sum(fn (Message $message): int => mb_strlen($message->content ?? ''));

        return mb_trim(view('ai.agents.tom', [
            'username' => $this->commit->user?->username,
            'nearlyFull' => $characters >= 9600 || $messages->count() >= 10,
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
                '@'.$commit->user?->username.': '.$commit->body,
            ))
            ->reverse()
            ->takeWhile(function (Message $message) use (&$remaining): bool {
                $remaining -= mb_strlen($message->content ?? '');

                return $remaining >= 0;
            })
            ->reverse()
            ->values();
    }

    /** @return list<Tool> */
    public function tools(): iterable
    {
        return [
            new SearchCommits,
            new SearchKnowledgeBase,
            new RepositoryInsights,
            new CommunityStats,
        ];
    }

    /** @return array<string, mixed> */
    public function providerOptions(Lab|string $provider): array
    {
        return ['thinking' => ['type' => 'disabled']];
    }
}
