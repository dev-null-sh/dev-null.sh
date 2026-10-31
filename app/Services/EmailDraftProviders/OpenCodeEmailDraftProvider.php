<?php

declare(strict_types=1);

namespace App\Services\EmailDraftProviders;

use App\Ai\Agents\EmailWriter;
use App\Contracts\Services\EmailDraftProvider;
use App\Queries\ChangelogQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * @phpstan-import-type ChangelogPullRequest from ChangelogQuery
 */
final readonly class OpenCodeEmailDraftProvider implements EmailDraftProvider
{
    public function draft(int $days, array $pullRequests): array
    {
        $response = new EmailWriter($days)->prompt(collect($pullRequests)
            ->map(fn (array $pullRequest): string => sprintf(
                '- [%s] %s (#%d, %s) by %s',
                $pullRequest['type'] ?? 'other',
                $pullRequest['title'],
                $pullRequest['number'],
                $pullRequest['url'],
                $pullRequest['author']['mention'] ?? $pullRequest['author']['login'],
            ))
            ->whenEmpty(fn (): Collection => collect(['No pull requests were merged.']))
            ->implode("\n"));

        $email = new Fluent($response->structured);

        return [
            'subject' => $email->string('subject')->value(),
            'body' => $email->string('body')->value(),
        ];
    }
}
