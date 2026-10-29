<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\EmailWriter;
use App\Enums\EmailDraft;
use App\Queries\ChangelogQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use RuntimeException;

/**
 * @phpstan-import-type ChangelogPullRequest from ChangelogQuery
 */
final readonly class EmailDrafts
{
    public function __construct(private ChangelogQuery $changelog)
    {
        //
    }

    /**
     * Drafts an email about what was merged into production since the draft looks back, reading the changelog three
     * days with merges at a time until it reaches that far.
     *
     * @return array{subject: string, body: string}
     */
    public function generate(EmailDraft $draft): array
    {
        $since = CarbonImmutable::now()->subDays($draft->days());
        $pullRequests = collect();
        $before = null;

        do {
            $changelog = $this->changelog->get('UTC', $before) ?? throw new RuntimeException('Could not read the changelog from GitHub.');
            $pullRequests->push(...$changelog['pullRequests']);
            $before = $changelog['olderBefore'] === null ? null : CarbonImmutable::parse($changelog['olderBefore']);
        } while ($before?->isAfter($since));

        $response = new EmailWriter($draft->days())->prompt($pullRequests
            ->filter(fn (array $pullRequest): bool => CarbonImmutable::parse($pullRequest['merged_at'])->isAfter($since))
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
