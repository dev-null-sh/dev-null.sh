<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Message;
use App\Models\MessageVoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowMessageVoiceRequest extends FormRequest
{
    /**
     * Voice notes never leave the server for anyone outside the conversation, or before the history a member can see.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $messageVoice = $this->route('messageVoice');
        assert($messageVoice instanceof MessageVoice);

        $message = Message::query()->find($messageVoice->message_id);

        return $message instanceof Message && $message->isVisibleTo($user) && Storage::disk()->exists($messageVoice->path)
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
