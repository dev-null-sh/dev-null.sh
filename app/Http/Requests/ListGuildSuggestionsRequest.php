<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListGuildSuggestionsRequest extends FormRequest
{
    public function authorize(Features $features): Response
    {
        return $features->available(Feature::Guilds, $this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100', 'prohibits:handles'],
            'handles' => ['sometimes', 'array', 'list', 'max:100'],
            'handles.*' => ['required', 'string', 'max:60', 'regex:/^\p{L}[\p{L}\p{M}\p{N}_]*$/uD'],
        ];
    }

    /** @return list<string> */
    public function handles(): array
    {
        /** @var list<string> $handles */
        $handles = $this->validated('handles', []);

        return array_values(array_unique(array_map(mb_strtolower(...), $handles)));
    }
}
