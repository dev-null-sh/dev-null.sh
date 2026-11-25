<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\Guild;
use App\Rules\FlaggedGuild;
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
    private bool $isFlagged = false;

    /**
     * The rules a guild edit follows, wherever it's made.
     *
     * @return array<string, list<string|In>>
     */
    public static function guildRules(EmojiShortcodes $emoji): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:500'],
            'emoji' => ['required', 'string', Rule::in($emoji->emoji())],
            'images' => ['nullable', 'list', 'max:1'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'remove_icon' => ['required', 'boolean'],
        ];
    }

    /**
     * Refuses a name that doesn't match the guild's hashtag. An edit that passes is moderated, and the answer says
     * whether the moderation flagged it.
     */
    public static function validateEdit(Validator $validator, FlaggedGuild $flagged, Guild $guild, string $name, string $description, ?UploadedFile $icon): bool
    {
        if (CreateGuildRequest::hashtagOf($name) !== $guild->handle) {
            $validator->errors()->add('name', "Guild display name doesn't match hashtag");

            return false;
        }

        return $flagged->validate($validator, $name."\n\n".$description, $icon);
    }

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
        return self::guildRules($emoji);
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
            $icon = $this->file('images.0');
            $this->isFlagged = self::validateEdit($validator, $flagged, $guild, $this->string('name')->value(), $this->string('description')->value(), $icon instanceof UploadedFile ? $icon : null);
        }];
    }

    /**
     * Whether the moderation flagged the edit, so it waits for an admin instead of being published.
     */
    public function isFlagged(): bool
    {
        return $this->isFlagged;
    }
}
