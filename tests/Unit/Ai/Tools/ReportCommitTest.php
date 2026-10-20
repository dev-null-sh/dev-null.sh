<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Ai\Agents\Tom;
use App\Ai\Tools\ReportCommit;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Laravel\Ai\Tools\Request;

function reportRequest(string $reason = 'ai_slop', string $assessment = 'Reporter says it reads as generated filler.'): Request
{
    return new Request(['reason' => $reason, 'assessment' => $assessment]);
}

it('reports the replied-to commit on behalf of the mention author, once', function (): void {
    $reporter = User::factory()->create();
    $target = Commit::factory()->create();
    $mention = Commit::factory()->for($reporter)->for($target, 'parent')->create(['body' => '@tom this looks like AI slop']);
    $tool = new ReportCommit($mention);

    expect($tool->handle(reportRequest()))->toStartWith('Sent to the moderators.')
        ->and($tool->handle(reportRequest()))->toBe('Already handled for this message.');
    new ReportCommit($mention)->handle(reportRequest());

    $report = CommitReport::query()->sole();
    expect($report->commit_id)->toBe($target->id)
        ->and($report->reporter_id)->toBe($reporter->id)
        ->and($report->reason)->toBe('ai_slop')
        ->and($report->details)->toBe('Filed through Tom. Reporter says it reads as generated filler.')
        ->and($report->dismissed_at)->toBeNull()
        ->and($target->user->hasRecentReports())->toBeTrue();
});

it('falls back to other for unknown reasons and caps the note at the details limit', function (): void {
    $mention = Commit::factory()->for(Commit::factory(), 'parent')->create();

    new ReportCommit($mention)->handle(reportRequest('made-up', str_repeat('a', 600)));

    $report = CommitReport::query()->sole();
    expect($report->reason)->toBe('other')
        ->and(mb_strlen((string) $report->details))->toBe(500);
});

it('refuses targets that cannot be reported', function (Closure $parent, string $message): void {
    $reporter = User::factory()->create();
    $mention = Commit::factory()->for($reporter)->create(['parent_id' => $parent($reporter)?->id]);

    expect(new ReportCommit($mention)->handle(reportRequest()))->toContain($message)
        ->and(CommitReport::query()->count())->toBe(0);
})->with([
    'top-level mention' => [fn (): null => null, 'no published post'],
    'pending post' => [fn (): Commit => Commit::factory()->pending()->create(), 'no published post'],
    'rejected post' => [fn (): Commit => Commit::factory()->rejected()->create(), 'no published post'],
    'deleted post' => [fn (): Commit => Commit::factory()->create(['deleted_at' => now()]), 'no published post'],
    'own post' => [fn (User $reporter): Commit => Commit::factory()->for($reporter)->create(), 'their own posts'],
    'tom post' => [fn (): Commit => Commit::factory()->for(User::factory()->state(['github_id' => CreateTomFriendship::GITHUB_ID]))->create(), 'replies to you'],
]);

it('limits each reporter to five reports a minute', function (): void {
    $reporter = User::factory()->create();

    $results = collect(range(1, 6))->map(fn (): string => new ReportCommit(
        Commit::factory()->for($reporter)->for(Commit::factory(), 'parent')->create(),
    )->handle(reportRequest()));

    expect($results->last())->toContain('too many reports')
        ->and(CommitReport::query()->count())->toBe(5);
});

it('gives tom the report tool', function (): void {
    $tools = collect(new Tom(Commit::factory()->create())->tools());

    expect($tools->contains(fn (object $tool): bool => $tool instanceof ReportCommit))->toBeTrue();
});
