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
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;

final class UpdateApiGuildRequest extends FormRequest
{
    private bool $isFlagged = false;

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
        return UpdateGuildRequest::guildRules($emoji);
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
            $this->isFlagged = UpdateGuildRequest::validateEdit($validator, $flagged, $guild, $this->string('name')->value(), $this->string('description')->value(), $icon instanceof UploadedFile ? $icon : null);
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
