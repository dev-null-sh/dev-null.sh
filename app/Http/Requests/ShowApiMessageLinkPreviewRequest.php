<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class ShowApiMessageLinkPreviewRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        $message = $this->route('message');
        assert($message instanceof Message);

        return $message->isVisibleTo($user) ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
