<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\Guild;
use App\Rules\FlaggedGuild;
use App\Services\Features;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

final class CreateGuildAliasRequest extends FormRequest
{
    private const int MAX_PER_GUILD = 10;

    public function authorize(Features $features): Response
    {
        $guild = $this->route('guild');
        assert($guild instanceof Guild);

        return $features->available(Feature::Guilds, $this->user()) && $guild->user_id === $this->user()?->id
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string|Unique>> */
    public function rules(): array
    {
        return [
            'handle' => ['required', 'string', 'max:60', 'regex:/^\p{L}[\p{L}\p{M}\p{N}_]*$/uD', Rule::unique('guilds', 'handle'), Rule::unique('guild_aliases', 'handle')],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(FlaggedGuild $flagged): array
    {
        return [function (Validator $validator) use ($flagged): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $guild = $this->route('guild');
            assert($guild instanceof Guild);

            if ($guild->aliases()->count() >= self::MAX_PER_GUILD) {
                $validator->errors()->add('handle', 'A guild can have up to '.self::MAX_PER_GUILD.' aliases.');

                return;
            }

            if ($flagged->validate($validator, '#'.$this->string('handle')->value(), null)) {
                $validator->errors()->add('handle', 'This hashtag cannot be used as an alias.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'handle.unique' => 'A guild already uses this hashtag.',
            'handle.regex' => 'Use a hashtag that starts with a letter and contains only letters, numbers and underscores.',
        ];
    }

    public function prepareForValidation(): void
    {
        if (is_string($this->input('handle'))) {
            $this->merge(['handle' => $this->string('handle')->trim()->ltrim('#')->lower()->value()]);
        }
    }
}
