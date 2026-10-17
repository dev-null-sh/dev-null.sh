<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dto\NewPoll;
use App\Enums\PollDuration;
use App\Models\User;
use App\Rules\ScheduledCommitLimit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;

final class CreateScheduledCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum>> */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'body' => $this->hasFile('images') ? CreateCommitRequest::BODY_WITH_IMAGES_RULES : CreateCommitRequest::BODY_RULES,
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:now', 'before_or_equal:'.now()->addDays(30)->toIso8601String(), new ScheduledCommitLimit($user)],
            'parent_id' => ['prohibited'],
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

    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }
}
