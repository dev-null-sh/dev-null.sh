<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateJoinLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The page the guest was on, as a path on this site, so the link in the email can only lead back here. `homepage`
     * is a field people never see, which bots fill in.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'path' => ['required', 'string', 'max:2000', 'regex:/^\/(?![\/\\\\])\S*$/'],
            'homepage' => ['nullable', 'string'],
        ];
    }
}
