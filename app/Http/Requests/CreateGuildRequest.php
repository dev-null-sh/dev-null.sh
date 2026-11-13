<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\Guild;
use App\Rules\UnflaggedGuild;
use App\Services\EmojiShortcodes;
use App\Services\Features;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;

final class CreateGuildRequest extends FormRequest
{
    private const int MAX_PER_USER = 100;

    public function authorize(Features $features): Response
    {
        return $features->available(Feature::Guilds, $this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string|In>> */
    public function rules(EmojiShortcodes $emoji): array
    {
        return [
            'handle' => ['required', 'string', 'max:60', 'regex:/^\p{L}[\p{L}\p{M}\p{N}_]*$/uD', 'unique:guilds,handle'],
            'name' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:500'],
            'emoji' => ['required', 'string', Rule::in($emoji->emoji())],
            'images' => ['nullable', 'list', 'max:1'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(UnflaggedGuild $unflagged): array
    {
        return [function (Validator $validator) use ($unflagged): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->string('name')->replaceMatches('/[^\p{L}\p{M}\p{N}_]+/u', '')->lower()->value() !== $this->string('handle')->value()) {
                $validator->errors()->add('name', "Guild display name doesn't match hashtag");
            }

            if (DB::table('guild_moderator_removals')->where('github_id', $this->user()?->github_id)->where('handle', $this->string('handle')->value())->exists()) {
                $validator->errors()->add('handle', 'You cannot reclaim a guild you were removed from.');
            }

            if (Guild::query()->where('user_id', $this->user()?->id)->count() >= self::MAX_PER_USER) {
                $validator->errors()->add('handle', 'You can create up to '.self::MAX_PER_USER.' guilds.');
            }

            if ($validator->errors()->isEmpty()) {
                $icon = $this->file('images.0');
                $unflagged->validate($validator, $this->string('name')->value()."\n\n".$this->string('description')->value(), $icon instanceof UploadedFile ? $icon : null);
            }
        }];
    }

    public function prepareForValidation(): void
    {
        if (! $this->has('handle') && is_string($this->input('name'))) {
            $this->merge(['handle' => $this->string('name')->replaceMatches('/[^\p{L}\p{M}\p{N}_]+/u', '')->lower()->value()]);
        }

        if (is_string($this->input('handle'))) {
            $this->merge(['handle' => $this->string('handle')->trim()->lower()->value()]);
        }
    }
}
