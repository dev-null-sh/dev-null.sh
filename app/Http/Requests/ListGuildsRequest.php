<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListGuildsRequest extends FormRequest
{
    public function authorize(Features $features): Response
    {
        return $features->available(Feature::Guilds, $this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'visible_guilds' => ['sometimes', 'array', 'list', 'max:24'],
            'visible_guilds.*' => ['required', 'string', 'max:60', 'regex:/^\p{L}[\p{L}\p{M}\p{N}_]*$/uD'],
        ];
    }

    public function term(): string
    {
        return $this->string('q')->trim()->ltrim('#')->trim()->value();
    }

    /** @return list<string> */
    public function visibleGuilds(): array
    {
        /** @var list<string> $handles */
        $handles = $this->validated('visible_guilds', []);

        return $handles;
    }

    public function prepareForValidation(): void
    {
        if ($this->hasHeader('X-Guild-Handles')) {
            $handles = json_decode($this->header('X-Guild-Handles', ''));
            $this->merge(['visible_guilds' => is_array($handles) ? $handles : null]);
        }
    }
}
