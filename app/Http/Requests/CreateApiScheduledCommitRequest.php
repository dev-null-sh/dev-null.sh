<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\NewPoll;
use App\Enums\PollDuration;
use App\Models\User;
use App\Rules\ScheduledCommitLimit;
use App\Services\Urls;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

/**
 * A commit queued from the app, sent as JSON, or with images as multipart, checked as the web checks one. It can't
 * reply to or quote a commit, as on the web.
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

    /** @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum|In>> */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);
        $body = $this->input('body');

        return [
            'body' => CreateCommitRequest::bodyRules($this->hasFile('images')),
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:now', 'before_or_equal:'.now()->addDays(30)->toIso8601String(), new ScheduledCommitLimit($user)],
            'parent_id' => ['prohibited'],
            'quoted_commit_id' => ['prohibited'],
            'link_preview_url' => ['nullable', 'string', Rule::in(is_string($body) ? resolve(Urls::class)->all($body) : [])],
            ...CreateCommitRequest::attachmentRules(),
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'link_preview_url.in' => 'The link card must be one of the links in the body.',
        ];
    }

    /**
     * The first link in the body gets the link preview, unless the author chose another link or none at all.
     */
    public function linkPreview(): bool|string
    {
        if (! $this->has('link_preview_url')) {
            return true;
        }

        return $this->filled('link_preview_url') ? $this->string('link_preview_url')->value() : false;
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
