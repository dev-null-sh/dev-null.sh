<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TalkVisibility;
use App\Models\Talk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

final class CreateTalkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|Enum>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:'.Talk::MIN_TITLE_LENGTH, 'max:'.Talk::MAX_TITLE_LENGTH],
            'description' => ['required', 'string', 'min:'.Talk::MIN_TITLE_LENGTH, 'max:'.Talk::MAX_DESCRIPTION_LENGTH],
            'presented_on' => ['required', 'date_format:Y-m-d'],
            'slides_url' => ['nullable', 'string', 'url', 'max:2048', 'starts_with:https://'],
            'recording_url' => ['nullable', 'string', 'url', 'max:2048', 'starts_with:https://'],
            'event_name' => ['nullable', 'string', 'max:'.Talk::MAX_EVENT_NAME_LENGTH],
            'visibility' => ['required', Rule::enum(TalkVisibility::class)],
        ];
    }

    public function visibility(): TalkVisibility
    {
        return TalkVisibility::from($this->string('visibility')->value());
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('slides_url') || $this->filled('recording_url')) {
                    return;
                }

                $validator->errors()->add('slides_url', 'Add a slides URL, a recording URL, or both.');
                $validator->errors()->add('recording_url', 'Add a slides URL, a recording URL, or both.');
            },
        ];
    }
}
