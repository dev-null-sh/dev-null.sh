<?php

declare(strict_types=1);

namespace App\Services\EmailDraftProviders;

use App\Ai\Agents\EmailWriter;
use App\Contracts\Services\EmailDraftProvider;
use App\Queries\ChangelogQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

/**
 * @phpstan-import-type ChangelogCommit from ChangelogQuery
 */
final readonly class OpenCodeEmailDraftProvider implements EmailDraftProvider
{
    public function draft(int $days, array $commits): array
    {
        $response = new EmailWriter($days)->prompt(collect($commits)
            ->map(fn (array $commit): string => sprintf(
                '- [%s] %s (%s) by %s',
                $commit['type'] ?? 'other',
                $commit['title'],
                $commit['pull_request']['url'] ?? $commit['url'],
                $commit['author']['mention'] ?? $commit['author']['login'],
            ))
            ->whenEmpty(fn (): Collection => collect(['Nothing landed on production.']))
            ->implode("\n"));

        $email = new Fluent($response->structured);

        return [
            'subject' => $email->string('subject')->value(),
            'body' => $email->string('body')->value(),
        ];
    }
}
