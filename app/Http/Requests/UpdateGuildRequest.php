<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\Guild;
use App\Models\User;
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

final class UpdateGuildRequest extends FormRequest
{
    public static function moderatorLimitMessage(): string
    {
        return 'This member already moderates '.CreateGuildRequest::MAX_PER_USER.' guilds.';
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
        return [
            'description' => ['required', 'string', 'max:500'],
            'emoji' => ['required', 'string', Rule::in($emoji->emoji())],
            'images' => ['nullable', 'list', 'max:1'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'remove_icon' => ['required', 'boolean'],
            'moderator_id' => ['sometimes', 'string', 'uuid'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(UnflaggedGuild $unflagged, Features $features): array
    {
        return [function (Validator $validator) use ($unflagged, $features): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $guild = $this->route('guild');
            assert($guild instanceof Guild);
            $this->validateModerator($validator, $guild, $features);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $icon = $this->file('images.0');
            $unflagged->validate($validator, $guild->name."\n\n".$this->string('description')->value(), $icon instanceof UploadedFile ? $icon : null);
        }];
    }

    public function moderator(): ?User
    {
        return $this->filled('moderator_id') ? User::query()->find($this->string('moderator_id')->value()) : null;
    }

    /**
     * Moderation can only go to someone who already joined the guild, can open guilds, wasn't removed from moderating it, and has
     * room for another guild.
     */
    private function validateModerator(Validator $validator, Guild $guild, Features $features): void
    {
        $moderator = $this->moderator();

        if (! $moderator instanceof User || $moderator->id === $this->user()?->id) {
            if ($this->filled('moderator_id') && ! $moderator instanceof User) {
                $validator->errors()->add('moderator_id', 'Pick a member of this guild.');
            }

            return;
        }

        if (! $guild->members()->whereKey($moderator->id)->exists()) {
            $validator->errors()->add('moderator_id', 'Pick a member of this guild.');
        } elseif (! $features->available(Feature::Guilds, $moderator)) {
            $validator->errors()->add('moderator_id', 'This member cannot open guilds yet.');
        } elseif (DB::table('guild_moderator_removals')->where('github_id', $moderator->github_id)->where('handle', $guild->handle)->exists()) {
            $validator->errors()->add('moderator_id', 'This member was removed as its moderator.');
        } elseif (Guild::query()->where('user_id', $moderator->id)->count() >= CreateGuildRequest::MAX_PER_USER) {
            $validator->errors()->add('moderator_id', self::moderatorLimitMessage());
        }
    }
}
