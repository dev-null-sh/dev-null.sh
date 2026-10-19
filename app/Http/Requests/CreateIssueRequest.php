<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

final class CreateIssueRequest extends FormRequest
{
    public function prepareForValidation(): void
    {
        CreateCommitRequest::normalizeBody($this);
    }

    /**
     * Issues take the images of a commit, but no poll.
     *
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum|In>>
     */
    public function rules(): array
    {
        $attachments = CreateCommitRequest::attachmentRules();

        return [
            'command' => ['required', Rule::in(['/feature', '/bug'])],
            'body' => CreateCommitRequest::BODY_RULES,
            'images' => $attachments['images'],
            'images.*' => $attachments['images.*'],
            'image_descriptions' => $attachments['image_descriptions'],
            'image_descriptions.*' => $attachments['image_descriptions.*'],
        ];
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
