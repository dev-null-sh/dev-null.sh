<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Feature;
use App\Models\User;
use App\Rules\FlaggedGuild;
use App\Services\EmojiShortcodes;
use App\Services\Features;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

final class CreateApiGuildRequest extends FormRequest
{
    private bool $isFlagged = false;

    public function authorize(Features $features): Response
    {
        return $features->available(Feature::Guilds, $this->user()) ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string|In|Unique>> */
    public function rules(EmojiShortcodes $emoji): array
    {
        return CreateGuildRequest::guildRules($emoji);
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
            $this->isFlagged = CreateGuildRequest::validateNewGuild($validator, $flagged, $user, $this->string('handle')->value(), $this->string('name')->value(), $this->string('description')->value(), $icon instanceof UploadedFile ? $icon : null);
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
        return CreateGuildRequest::guildMessages();
    }

    public function prepareForValidation(): void
    {
        if (! $this->has('handle') && is_string($this->input('name'))) {
            $this->merge(['handle' => CreateGuildRequest::hashtagOf($this->string('name')->value())]);
        }

        if (is_string($this->input('handle'))) {
            $this->merge(['handle' => $this->string('handle')->trim()->lower()->value()]);
        }
    }
}
