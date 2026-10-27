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

final readonly class EmailDrafts
{
    public function __construct(private ChangelogQuery $changelog)
    {
        //
    }

    /**
     * Drafts an email about what was committed to production since the draft looks back, reading the changelog three
     * days with commits at a time until it reaches that far.
     *
     * @return array{subject: string, body: string}
     */
    public function generate(EmailDraft $draft): array
    {
        $since = CarbonImmutable::now()->subDays($draft->days());
        $commits = collect();
        $before = null;

        do {
            $changelog = $this->changelog->get('UTC', $before) ?? throw new RuntimeException('Could not read the changelog from GitHub.');
            $commits->push(...$changelog['commits']);
            $before = $changelog['olderBefore'] === null ? null : CarbonImmutable::parse($changelog['olderBefore']);
        } while ($before?->isAfter($since));

        $response = new EmailWriter($draft->days())->prompt($commits
            ->filter(fn (array $commit): bool => CarbonImmutable::parse($commit['committed_at'])->isAfter($since))
            ->map(fn (array $commit): string => sprintf(
                '- [%s] %s (%s) by %s',
                $commit['type'] ?? 'other',
                $commit['title'],
                $commit['pull_request'] === null ? $commit['url'] : "#{$commit['pull_request']['number']}, {$commit['pull_request']['url']}",
                $commit['author']['mention'] ?? $commit['author']['login'],
            ))
            ->whenEmpty(fn (): Collection => collect(['Nothing was committed.']))
            ->implode("\n"));

        $email = new Fluent($response->structured);

        return [
            'subject' => $email->string('subject')->value(),
            'body' => $email->string('body')->value(),
        ];
    }
}
