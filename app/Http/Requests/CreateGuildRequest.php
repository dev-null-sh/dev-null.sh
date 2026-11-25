<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\Guild;
use App\Models\GuildSubmission;
use App\Models\User;
use App\Rules\FlaggedGuild;
use App\Services\EmojiShortcodes;
use App\Services\Features;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

final class CreateGuildRequest extends FormRequest
{
    public const string RECLAIM_REFUSED = 'You cannot reclaim a guild you were removed from.';

    private const int MAX_PER_USER = 100;

    private bool $isFlagged = false;

    /**
     * The rules a new guild follows, wherever it's created.
     *
     * @return array<string, list<string|In|Unique>>
     */
    public static function guildRules(EmojiShortcodes $emoji): array
    {
        return [
            'handle' => ['required', 'string', 'max:60', 'regex:/^\p{L}[\p{L}\p{M}\p{N}_]*$/uD', Rule::unique('guilds', 'handle')->whereNotNull('user_id')],
            'name' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:500'],
            'emoji' => ['required', 'string', Rule::in($emoji->emoji())],
            'images' => ['nullable', 'list', 'max:1'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    /**
     * Refuses a name that doesn't match the hashtag, a guild the user was removed from, and more guilds than the user
     * may have, counting the ones waiting for review. A guild that passes is moderated, and the answer says whether the
     * moderation flagged it.
     */
    public static function validateNewGuild(Validator $validator, FlaggedGuild $flagged, User $user, string $handle, string $name, string $description, ?UploadedFile $icon): bool
    {
        if (self::hashtagOf($name) !== $handle) {
            $validator->errors()->add('name', "Guild display name doesn't match hashtag");
        }

        if (DB::table('guild_moderator_removals')->where('github_id', $user->github_id)->where('handle', $handle)->exists()) {
            $validator->errors()->add('handle', self::RECLAIM_REFUSED);
        }

        $guilds = Guild::query()->where('user_id', $user->id);
        $submissions = GuildSubmission::query()->where('user_id', $user->id)->where('handle', '!=', $handle)->whereNotIn('handle', (clone $guilds)->select('handle'));

        if ($guilds->count() + $submissions->count() >= self::MAX_PER_USER) {
            $validator->errors()->add('handle', 'You can create up to '.self::MAX_PER_USER.' guilds.');
        }

        return $validator->errors()->isEmpty() && $flagged->validate($validator, $name."\n\n".$description, $icon);
    }

    /**
     * The hashtag a display name stands for: its letters, numbers and underscores, lowercased.
     */
    public static function hashtagOf(string $name): string
    {
        return Str::of($name)->replaceMatches('/[^\p{L}\p{M}\p{N}_]+/u', '')->lower()->value();
    }

    /** @return array<string, string> */
    public static function guildMessages(): array
    {
        return [
            'handle.unique' => 'A guild already uses this hashtag. Choose a different display name.',
            'handle.regex' => 'Use a display name whose hashtag starts with a letter and contains only letters, numbers and underscores.',
        ];
    }

    public function authorize(Features $features): Response
    {
        return $features->available(Feature::Guilds, $this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string|In|Unique>> */
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

            $user = $this->user();
            assert($user instanceof User);
            $icon = $this->file('images.0');
            $this->isFlagged = self::validateNewGuild($validator, $flagged, $user, $this->string('handle')->value(), $this->string('name')->value(), $this->string('description')->value(), $icon instanceof UploadedFile ? $icon : null);
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
        return self::guildMessages();
    }

    public function prepareForValidation(): void
    {
        if (! $this->has('handle') && is_string($this->input('name'))) {
            $this->merge(['handle' => self::hashtagOf($this->string('name')->value())]);
        }

        if (is_string($this->input('handle'))) {
            $this->merge(['handle' => $this->string('handle')->trim()->lower()->value()]);
        }
    }
}
