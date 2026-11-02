<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Message;
use App\Models\MessageVoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowApiMessageVoiceRequest extends FormRequest
{
    /**
     * The URL was signed for one person in the conversation, so it stops working as soon as they can no longer see
     * the message, as the web's voice note does for them, as well as when it expires.
     */
    public function authorize(): Response
    {
        $messageVoice = $this->route('messageVoice');
        assert($messageVoice instanceof MessageVoice);

        $user = User::query()->find($this->string('user')->value());
        $message = Message::query()->find($messageVoice->message_id);

        return $user instanceof User && $message instanceof Message && $message->isVisibleTo($user) && Storage::disk()->exists($messageVoice->path)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
