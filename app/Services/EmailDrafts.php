<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\EmailDraftProvider;
use App\Enums\EmailDraft;
use App\Queries\ChangelogQuery;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class EmailDrafts
{
    public function __construct(private ChangelogQuery $changelog, private EmailDraftProvider $emails)
    {
        //
    }

    /**
     * Drafts an email about what landed on production since the draft looks back, reading the changelog three
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

        return $this->emails->draft($draft->days(), array_values($commits
            ->filter(fn (array $commit): bool => CarbonImmutable::parse($commit['committed_at'])->isAfter($since))
            ->all()));
    }
}
