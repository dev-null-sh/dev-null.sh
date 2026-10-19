<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Models\User;
use App\Services\PasskeyChallenges;
use App\Services\SignedWebCommits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * A draft is only checked as far as the signature needs; the commit or issue itself is validated when it is posted.
 */
final class CreateSigningChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->passkeys()->exists();
    }

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        /** @var list<int> $passkeyIds */
        $passkeyIds = $user->passkeys()->pluck('id')->all();

        return [
            'action' => ['nullable', 'string', Rule::in(['enable', 'disable', 'add', ...array_map(fn (int $id): string => "remove:{$id}", $passkeyIds)])],
            'body' => ['nullable', 'string'],
            'command' => ['nullable', Rule::in(['/feature', '/bug'])],
            'parent_id' => ['nullable', 'uuid'],
            'quoted_commit_id' => ['nullable', 'uuid'],
            'scheduled_at' => ['nullable', 'string'],
            'poll' => ['nullable', 'array:options,duration'],
            'poll.options' => ['required_with:poll', 'list'],
            'poll.options.*' => ['string'],
            'poll.duration' => ['required_with:poll', 'integer'],
            'image_hashes' => ['nullable', 'list', 'max:'.CreateCommitImages::MAX_IMAGES],
            'image_hashes.*' => ['string', 'regex:/^[0-9a-f]{64}$/'],
        ];
    }

    public function statement(): string
    {
        if ($this->filled('action')) {
            return PasskeyChallenges::settings($this->string('action')->value());
        }

        if ($this->filled('command')) {
            return PasskeyChallenges::gitHubIssue($this->string('command')->value(), $this->string('body')->value());
        }

        /** @var list<string> $imageHashes */
        $imageHashes = $this->array('image_hashes');

        return SignedWebCommits::statement(
            $this,
            $this->filled('parent_id') ? $this->string('parent_id')->value() : null,
            $this->filled('scheduled_at') ? $this->string('scheduled_at')->value() : null,
            $imageHashes,
        );
    }
}
