<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Passkey;
use App\Models\User;
use App\Services\PasskeyChallenges;
use App\Services\SignedWebCommits;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class DeletePasskeyRequest extends FormRequest
{
    public function authorize(): Response
    {
        $passkey = $this->route('passkey');
        assert($passkey instanceof Passkey);

        return $passkey->user_id === $this->user()?->getAuthIdentifier() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return SignedWebCommits::rules();
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        $user = $this->user();
        assert($user instanceof User);
        $passkey = $this->route('passkey');
        assert($passkey instanceof Passkey);

        if (! $user->requires_signed_commits) {
            return [];
        }

        if ($user->passkeys()->count() === 1) {
            return [function (Validator $validator): void {
                $validator->errors()->add('signature', 'Turn off signed commits before you remove your last passkey.');
            }];
        }

        return [resolve(PasskeyChallenges::class)->confirmation($this, $user, "remove:{$passkey->id}")];
    }
}
