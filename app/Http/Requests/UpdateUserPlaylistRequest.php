<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\PlaylistUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserPlaylistRequest extends FormRequest
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
            'playlist_url' => ['bail', 'required', 'string', 'max:255', new PlaylistUrl()],
        ];
    }

    public function playlistUrl(): string
    {
        return $this->string('playlist_url')->value();
    }
}
