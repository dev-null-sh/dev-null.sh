<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\Guild;
use App\Models\GuildSubmission;
use App\Rules\FlaggedGuild;
use App\Services\EmojiShortcodes;
use App\Services\Features;
use App\Services\Hashtags;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

final class CreateGuildRequest extends FormRequest
{
    private const int MAX_PER_USER = 100;

    private bool $isFlagged = false;

    public function authorize(Features $features): Response
    {
        return $features->available(Feature::Guilds, $this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string|In|Unique>> */
    public function rules(EmojiShortcodes $emoji): array
    {
        return [
            'handle' => ['required', 'string', 'max:60', 'regex:/^'.Hashtags::HANDLE.'$/uD', Rule::unique('guilds', 'handle')->whereNotNull('user_id')],
            'name' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:500'],
            'emoji' => ['required', 'string', Rule::in($emoji->emoji())],
            'images' => ['nullable', 'list', 'max:1'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(FlaggedGuild $flagged): array
    {
        return [function (Validator $validator) use ($flagged): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (Hashtags::handleFor($this->string('name')->value()) !== $this->string('handle')->value()) {
                $validator->errors()->add('name', "Guild display name doesn't match hashtag");
            }

            if (DB::table('guild_moderator_removals')->where('github_id', $this->user()?->github_id)->where('handle', $this->string('handle')->value())->exists()) {
                $validator->errors()->add('handle', 'You cannot reclaim a guild you were removed from.');
            }

            $guilds = Guild::query()->where('user_id', $this->user()?->id);
            $submissions = GuildSubmission::query()->where('user_id', $this->user()?->id)->where('handle', '!=', $this->string('handle')->value())->whereNotIn('handle', (clone $guilds)->select('handle'));

            if ($guilds->count() + $submissions->count() >= self::MAX_PER_USER) {
                $validator->errors()->add('handle', 'You can create up to '.self::MAX_PER_USER.' guilds.');
            }

            if ($validator->errors()->isEmpty()) {
                $icon = $this->file('images.0');
                $this->isFlagged = $flagged->validate($validator, $this->string('name')->value()."\n\n".$this->string('description')->value(), $icon instanceof UploadedFile ? $icon : null);
            }
        }];
    }

    /**
     * Whether the moderation flagged the guild, so it waits for an admin instead of being published.
     */
    public function isFlagged(): bool
    {
        return $this->isFlagged;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'handle.unique' => 'A guild already uses this hashtag. Choose a different display name.',
            'handle.regex' => 'Use a display name whose hashtag starts with letters or numbers followed by a letter, contains only letters, numbers and underscores, and is not a hex color that starts with a number.',
        ];
    }

    public function prepareForValidation(): void
    {
        if (! $this->has('handle') && is_string($this->input('name'))) {
            $this->merge(['handle' => Hashtags::handleFor($this->string('name')->value())]);
        }

        if (is_string($this->input('handle'))) {
            $this->merge(['handle' => $this->string('handle')->trim()->lower()->value()]);
        }
    }
}
