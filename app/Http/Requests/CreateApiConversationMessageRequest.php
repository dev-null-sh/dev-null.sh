<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PushAccess;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Queries\PushAccessQuery;
use App\Services\VoiceNotes;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

final class CreateApiConversationMessageRequest extends FormRequest
{
    /**
     * A request can't be answered before it is merged, and a sender gets one message in until it is.
     */
    public function authorize(PushAccessQuery $access): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $partner = $this->route('partner');
        assert($partner instanceof User);

        if (! $user->canMessage($partner)) {
            return Response::denyAsNotFound();
        }

        return match ($access->get($user, $partner)) {
            PushAccess::Open => Response::allow(),
            PushAccess::Request => Response::deny("Merge @{$partner->handle}'s request before replying."),
            PushAccess::Waiting => Response::deny("@{$partner->handle} hasn't merged your request yet."),
            PushAccess::Restricted => Response::deny("@{$partner->handle} doesn't follow you, so you can't push to them yet."),
            PushAccess::Closed => Response::deny("@{$partner->handle} isn't taking remotes."),
        };
    }

    /**
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum|In>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        $partner = $this->route('partner');
        assert($partner instanceof User);

        return CreateConversationMessageRequest::messageRules($this, Conversation::between($user, $partner), 0);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'link_preview_url.in' => 'The link card must be one of the links in the message.',
        ];
    }

    public function replyTo(): ?Message
    {
        return $this->filled('reply_to_id') ? Message::query()->find($this->integer('reply_to_id')) : null;
    }

    /**
     * The first link in the message gets the link card, unless the sender chose another link or none at all.
     */
    public function linkPreview(): bool|string
    {
        if (! $this->has('link_preview_url')) {
            return true;
        }

        return $this->filled('link_preview_url') ? $this->string('link_preview_url')->value() : false;
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_values($images) : [];
    }

    /**
     * @return list<string|null>
     */
    public function imageDescriptions(): array
    {
        /** @var list<string|null> $descriptions */
        $descriptions = $this->array('image_descriptions');

        return $descriptions;
    }

    public function voice(): ?UploadedFile
    {
        $voice = $this->file('voice');

        return $voice instanceof UploadedFile ? $voice : null;
    }

    /**
     * @return list<int>
     */
    public function voiceWaveform(): array
    {
        return $this->filled('voice_waveform')
            ? array_map(fn (int $bar): int => $this->integer('voice_waveform.'.$bar), range(0, VoiceNotes::BARS - 1))
            : [];
    }
}
