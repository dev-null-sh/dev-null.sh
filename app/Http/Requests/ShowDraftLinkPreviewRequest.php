<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Urls;
use Illuminate\Foundation\Http\FormRequest;

final class ShowDraftLinkPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url:http,https', 'max:'.Urls::MAX_LENGTH],
        ];
    }
}
