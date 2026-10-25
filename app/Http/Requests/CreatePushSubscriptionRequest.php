<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PushSubscription;
use App\Models\User;
use App\Rules\PushEndpoint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreatePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        assert($user instanceof User);

        return $user->remotes_enabled && config('services.web_push.public_key') && config('services.web_push.private_key');
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['endpoint' => ['required', 'string', 'max:2048', new PushEndpoint]];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            assert($user instanceof User);

            if (! $user->pushSubscriptions()->where('endpoint_hash', hash('sha256', $this->string('endpoint')->toString()))->exists()
                && $user->pushSubscriptions()->count() >= PushSubscription::MAX_PER_USER) {
                $validator->errors()->add('endpoint', 'You have off-site notifications enabled in '.PushSubscription::MAX_PER_USER.' browsers or apps. Turn them off in one before enabling another.');
            }
        }];
    }
}
