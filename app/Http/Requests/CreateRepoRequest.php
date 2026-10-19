<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Repo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class CreateRepoRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50', 'regex:/[a-z0-9]/i'],
            'description' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && Repo::query()->where('slug', $this->slug())->exists()) {
                $validator->errors()->add('name', 'A repo with that name already exists.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'The name needs at least one letter or number.',
        ];
    }

    public function name(): string
    {
        return $this->string('name')->trim()->value();
    }

    public function slug(): string
    {
        return Str::slug($this->name());
    }

    public function description(): ?string
    {
        return $this->filled('description') ? $this->string('description')->trim()->value() : null;
    }
}
