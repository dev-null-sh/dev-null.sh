<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Message;
use App\Models\MessageImage;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

final class ShowMessageImageRequest extends FormRequest
{
    /**
     * Images never leave the server for anyone outside the conversation, or before the history a member can see.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $messageImage = $this->route('messageImage');
        assert($messageImage instanceof MessageImage);

        $message = Message::query()->find($messageImage->message_id);

        return $message instanceof Message && $message->isVisibleTo($user) && Storage::disk()->exists($messageImage->path)
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

    public function download(): bool
    {
        return $this->boolean('download');
    }
}
