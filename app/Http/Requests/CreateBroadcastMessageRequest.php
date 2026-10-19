<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\In;

final class CreateBroadcastMessageRequest extends FormRequest
{
    /**
     * The owner and the publishers alone push to a broadcast; subscribers and everyone else cannot, and someone who
     * left or was never in it finds nothing here at all.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);
        $role = $broadcast->activeBroadcastRoleOf($user);

        return match (true) {
            ! $user->remotes_enabled || ! $broadcast->isBroadcast() => Response::denyAsNotFound(),
            ! $role instanceof BroadcastRole => Response::denyAsNotFound(),
            $role !== BroadcastRole::Owner && $role !== BroadcastRole::Publisher => Response::deny('Only the owner and publishers can post.'),
            default => Response::allow(),
        };
    }

    /**
     * @return array<string, array<int, string|ValidationRule|File|Dimensions|Enum|In>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        $broadcast = $this->route('broadcast');
        assert($broadcast instanceof Conversation);

        $visibleFrom = $broadcast->participants()->where('user_id', $user->id)->value('visible_from_id');

        return CreateConversationMessageRequest::messageRules($this, $broadcast, is_int($visibleFrom) ? $visibleFrom : 0);
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
}
