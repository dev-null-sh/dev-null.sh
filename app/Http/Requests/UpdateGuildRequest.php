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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;

final class UpdateGuildRequest extends FormRequest
{
    public function authorize(Features $features): Response
    {
        $guild = $this->route('guild');
        assert($guild instanceof Guild);

        return $features->available(Feature::Guilds, $this->user()) && $guild->user_id === $this->user()?->id
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string|In>> */
    public function rules(EmojiShortcodes $emoji): array
    {
        return [
            'description' => ['required', 'string', 'max:500'],
            'emoji' => ['required', 'string', Rule::in($emoji->emoji())],
            'images' => ['nullable', 'list', 'max:1'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'remove_icon' => ['required', 'boolean'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(UnflaggedGuild $unflagged): array
    {
        return [function (Validator $validator) use ($unflagged): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $guild = $this->route('guild');
            assert($guild instanceof Guild);
            $icon = $this->file('images.0');
            $unflagged->validate($validator, $guild->name."\n\n".$this->string('description')->value(), $icon instanceof UploadedFile ? $icon : null);
        }];
    }
}
