<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\NewPoll;
use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\User;
use App\Rules\VisibleCommit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;

/**
 * A commit from the mobile app, sent as JSON, or with images as multipart (see SignedRequests). It may reply to a commit
 * or quote one, as on the web, but not both. The images are checked as the web checks them.
 */
final class CreateApiCommitRequest extends FormRequest
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
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);
        $attachmentRules = CreateCommitRequest::attachmentRules();

        return [
            'body' => CreateCommitRequest::bodyRules($this->hasFile('images')),
            'parent_id' => ['bail', 'nullable', 'uuid', new VisibleCommit($user)],
            'quoted_commit_id' => ['bail', 'nullable', 'uuid', 'prohibits:parent_id', new VisibleCommit($user, __('That commit is no longer available to quote.'))],
            'poll' => [...$attachmentRules['poll'], 'prohibits:quoted_commit_id'],
            'poll.options' => $attachmentRules['poll.options'],
            'poll.options.*' => $attachmentRules['poll.options.*'],
            'poll.duration' => $attachmentRules['poll.duration'],
            'images' => $attachmentRules['images'],
            'images.*' => $attachmentRules['images.*'],
            'image_descriptions' => $attachmentRules['image_descriptions'],
            'image_descriptions.*' => $attachmentRules['image_descriptions.*'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'image_descriptions.*' => 'image description',
        ];
    }

    /**
     * A parent that went away since it was validated is a 404, so a reply never turns into a top-level commit.
     */
    public function parent(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('parent_id')
            ? Commit::query()->visibleTo($user)->findOrFail($this->string('parent_id')->value())
            : null;
    }

    public function quotedCommit(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('quoted_commit_id')
            ? Commit::query()->visibleTo($user)->find($this->string('quoted_commit_id')->value())
            : null;
    }

    public function poll(): ?NewPoll
    {
        if (! $this->filled('poll')) {
            return null;
        }

        /** @var list<string> $options */
        $options = $this->array('poll.options');

        return new NewPoll($options, PollDuration::from($this->integer('poll.duration')));
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_values($images) : [];
    }

    /**
     * @return list<string|null>
     */
    public function imageDescriptions(): array
    {
        /** @var list<string|null> $descriptions */
        $descriptions = $this->array('image_descriptions');

        return $descriptions;
    }
}
