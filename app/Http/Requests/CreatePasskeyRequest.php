<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\PasskeyChallenges;
use App\Services\Passkeys;
use App\Services\SignedWebCommits;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class CreatePasskeyRequest extends FormRequest
{
    /**
     * @var array{public_key: string, algorithm: int, sign_count: int}|null
     */
    private ?array $credential = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'credential' => ['required', 'array:credential_id,client_data_json,authenticator_data,public_key,algorithm'],
            'credential.credential_id' => ['required', 'string', 'max:255'],
            'credential.client_data_json' => ['required', 'string', 'max:8192'],
            'credential.authenticator_data' => ['required', 'string', 'max:8192'],
            'credential.public_key' => ['required', 'string', 'max:8192'],
            'credential.algorithm' => ['required', 'integer'],
            ...SignedWebCommits::rules(),
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [...($user->requires_signed_commits ? [resolve(PasskeyChallenges::class)->confirmation($this, $user, 'add')] : []), function (Validator $validator) use ($user): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                $this->credential = resolve(Passkeys::class)->checkRegistration($user, [
                    'credential_id' => $this->string('credential.credential_id')->value(),
                    'client_data_json' => $this->string('credential.client_data_json')->value(),
                    'authenticator_data' => $this->string('credential.authenticator_data')->value(),
                    'public_key' => $this->string('credential.public_key')->value(),
                    'algorithm' => $this->integer('credential.algorithm'),
                ]);
            } catch (InvalidArgumentException $invalidArgumentException) {
                $validator->errors()->add('credential', $invalidArgumentException->getMessage());
            }
        }];
    }

    /**
     * @return array{public_key: string, algorithm: int, sign_count: int}
     */
    public function credential(): array
    {
        assert($this->credential !== null);

        return $this->credential;
    }
}
