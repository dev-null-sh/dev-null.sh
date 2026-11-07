<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CreateProfileLookupRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'handle' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/',
                Rule::unique('profiles', 'handle'),
                // GitHub said there's no such account a moment ago, so it isn't asked again for a day...
                function (string $attribute, string $value, Closure $fail): void {
                    if (Cache::has('github-profiles:not-found:'.Str::lower($value))) {
                        $fail(__('GitHub has no @:handle.', ['handle' => $value]));
                    }
                },
            ],
        ];
    }

    public function handle(): string
    {
        return $this->string('handle')->value();
    }
}
