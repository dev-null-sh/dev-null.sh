<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\InboxTab;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class ListInboxRequest extends FormRequest
{
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->remotes_enabled ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', config('remotes.requests') ? Rule::enum(InboxTab::class) : Rule::enum(InboxTab::class)->except(InboxTab::Fetched)],
            'q' => ['nullable', 'string', 'max:100'],
            'before' => ['nullable', 'uuid'],
            'notification_baseline' => ['sometimes', 'boolean'],
        ];
    }

    public function notificationBaseline(): bool
    {
        return $this->boolean('notification_baseline');
    }

    public function tab(): InboxTab
    {
        return $this->enum('tab', InboxTab::class) ?? InboxTab::Open;
    }

    public function term(): string
    {
        return $this->string('q')->trim()->ltrim('@')->lower()->value();
    }

    public function beforeId(): ?string
    {
        return $this->filled('before') ? $this->string('before')->value() : null;
    }
}
