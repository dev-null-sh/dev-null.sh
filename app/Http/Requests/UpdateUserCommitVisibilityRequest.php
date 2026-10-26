<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

final class UpdateUserCommitVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|In>>
     */
    public function rules(): array
    {
        return [
            'hidden_commit_channels' => ['present', 'array', 'list', 'max:3'],
            'hidden_commit_channels.*' => ['required', 'string', 'distinct', Rule::in(['cli', 'ci', 'ai'])],
        ];
    }

    /**
     * @return list<string>
     */
    public function channels(): array
    {
        /** @var list<string> $channels */
        $channels = $this->array('hidden_commit_channels');

        return $channels;
    }
}
