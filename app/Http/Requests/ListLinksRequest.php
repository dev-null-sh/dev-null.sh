<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Urls;
use Illuminate\Foundation\Http\FormRequest;

final class ListLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'url' => ['nullable', 'string', 'max:'.Urls::MAX_LENGTH],
        ];
    }

    /**
     * The link as a commit would store it: a bare domain gets the same https scheme as in its link card.
     */
    public function link(): ?string
    {
        if (! $this->filled('url')) {
            return null;
        }

        $url = $this->string('url')->trim()->value();

        return preg_match('/^https?:\/\//i', $url) === 1 ? $url : 'https://'.$url;
    }
}
