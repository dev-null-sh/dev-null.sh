<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\ScheduledCommitLimit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A commit from the mobile app to submit later, sent as JSON, with the web's rules: a top-level commit, due within 30
 * days, and at most 10 waiting at once. Like a commit from the app, it has no images or poll yet.
 */
final class CreateApiScheduledCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Blank lines are collapsed before the body is measured, as on the web.
     */
    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'body' => CreateCommitRequest::bodyRules(),
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:now', 'before_or_equal:'.now()->addDays(30)->toIso8601String(), new ScheduledCommitLimit($user)],
            'parent_id' => ['prohibited'],
            'quoted_commit_id' => ['prohibited'],
        ];
    }

    /**
     * When the commit is due, in UTC.
     */
    public function scheduledAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('scheduled_at')->value())->utc();
    }
}
