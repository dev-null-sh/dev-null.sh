<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Services\Features;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ListApiGuildSuggestionsRequest extends FormRequest
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
        ];
    }

    /**
     * What follows the hashtag, so an app can send the word it is completing with or without its #.
     */
    public function term(): string
    {
        return $this->string('q')->trim()->ltrim('#')->trim()->lower()->value();
    }
}
