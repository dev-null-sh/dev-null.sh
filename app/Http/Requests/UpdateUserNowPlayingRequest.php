<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\LastFmUsername;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserNowPlayingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'lastfm_username' => ['bail', 'required', 'string', new LastFmUsername()],
        ];
    }

    public function lastFmUsername(): string
    {
        return $this->string('lastfm_username')->value();
    }
}
