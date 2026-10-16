<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ShowKeyOwnerRequest extends FormRequest
{
    /**
     * Only the `ssh:serve` command may look up key owners: it sends the token both sides share.
     */
    public function authorize(): bool
    {
        $token = config('ssh.lookup_token');

        return is_string($token) && $token !== '' && hash_equals($token, $this->bearerToken() ?? '');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'fingerprint' => ['required', 'string', 'max:100'],
        ];
    }

    public function fingerprint(): string
    {
        return $this->string('fingerprint')->toString();
    }
}
