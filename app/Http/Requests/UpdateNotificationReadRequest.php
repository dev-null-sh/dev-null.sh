<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotificationReadRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);
        $notification = $this->route('notification');
        assert($notification instanceof Notification);

        return $user->notifications()->relevant()->whereKey($notification->id)->exists()
            ? Response::allow() : Response::denyAsNotFound();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'read' => ['required', 'boolean'],
            'group' => ['required', 'boolean'],
            'type' => ['required', 'in:all,replies,mentions,follows,moderation'],
        ];
    }

    public function type(): string
    {
        return $this->string('type')->value();
    }
}
