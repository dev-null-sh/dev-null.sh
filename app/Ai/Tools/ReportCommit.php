<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\CreateCommitReport;
use App\Actions\CreateTomFriendship;
use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\CommitReport;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The reporter and the reported commit come from the mention itself, never from the model, so untrusted thread text can
 * only decide whether a report is filed and what note moderators read, not who is reported or on whose behalf.
 */
final class ReportCommit implements Tool
{
    private bool $called = false;

    public function __construct(private readonly Commit $mention)
    {
        //
    }

    public function description(): string
    {
        return "Privately send the post that the latest message replies to for review by human moderators, on behalf of the latest message's author. Only use it when that author explicitly asks you to flag or report it. You never decide the outcome; moderators do.";
    }

    public function handle(Request $request): string
    {
        if ($this->called) {
            return 'Already handled for this message.';
        }

        $this->called = true;

        $reporter = $this->mention->user;
        $target = $this->mention->parent()->withTrashed()->with('user')->first();

        if (! $reporter instanceof User || ! $target instanceof Commit || $target->trashed()
            || $target->status !== CommitStatus::Approved || ! $target->isVisibleTo($reporter)) {
            return 'Not reported: there is no published post this message replies to. Ask them to reply directly to the post they want reviewed.';
        }

        if ($target->user?->github_id === CreateTomFriendship::GITHUB_ID) {
            return 'Not reported: this message replies to you, not to the post they mean. Ask them to reply to that post directly.';
        }

        if ($target->user_id === $reporter->id) {
            return 'Not reported: people cannot report their own posts.';
        }

        if (RateLimiter::tooManyAttempts($key = 'tom-commit-reports:'.$reporter->id, 5)) {
            return 'Not reported: too many reports in the last minute. Ask them to try again shortly.';
        }

        RateLimiter::hit($key);

        $reason = $request->string('reason')->value();

        resolve(CreateCommitReport::class)->handle(
            $reporter,
            $target,
            array_key_exists($reason, CommitReport::REASONS) ? $reason : 'other',
            mb_substr('Filed through Tom. '.mb_trim($request->string('assessment')->value()), 0, 500),
        );

        return 'Sent to the moderators. Acknowledge briefly and neutrally, without judging the post or its author.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()->enum(array_keys(CommitReport::REASONS))->description('ai_slop for low-effort AI-generated content; otherwise the closest reason.')->required(),
            'assessment' => $schema->string()->max(400)->description('One or two sentences for moderators: what the reporter objected to and your honest, tentative read of the post. Never claim certainty that text is AI-generated.')->required(),
        ];
    }
}
