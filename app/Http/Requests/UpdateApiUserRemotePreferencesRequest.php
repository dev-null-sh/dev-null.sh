<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RemotesFrom;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdateApiUserRemotePreferencesRequest extends FormRequest
{
    /**
     * The settings live on the Remotes page, which isn't there while Remotes are off.
     */
    public function authorize(): Response
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->remotes_enabled ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Everyone is only offered while the site lets people take remotes from everyone.
     *
     * @return array<string, list<string|Enum>>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', config('remotes.everyone') ? Rule::enum(RemotesFrom::class) : Rule::enum(RemotesFrom::class)->only(RemotesFrom::Following)],
            'pull_receipts' => ['required', 'boolean'],
        ];
    }

    public function remotesFrom(): RemotesFrom
    {
        return $this->enum('from', RemotesFrom::class) ?? RemotesFrom::Following;
    }
}
