<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Models\User;
use App\Rules\DevNullUsername;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;
use LogicException;

final class UpdateUserProfileDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, array<int, string|ValidationRule|File|Dimensions>>
     */
    public function rules(): array
    {
        return [
            'dev_null_username' => [
                'present',
                'nullable',
                'string',
                'max:39',
                new DevNullUsername($this->user()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['present', 'nullable', 'string', 'max:160'],
            'company' => ['present', 'nullable', 'string', 'max:255'],
            'location' => ['present', 'nullable', 'string', 'max:255'],
            'website' => ['present', 'nullable', 'string', 'max:255', 'url:http,https'],
            'sponsors_url' => ['present', 'nullable', 'string', 'max:2048', 'url:http,https'],
            'avatar' => [
                'nullable',
                'image',
                File::types(['image/jpeg', 'image/png', 'image/webp'])->max(CreateCommitImages::MAX_UPLOAD_SIZE),
                Rule::dimensions()
                    ->maxWidth(CreateCommitImages::MAX_SOURCE_DIMENSION)
                    ->maxHeight(CreateCommitImages::MAX_SOURCE_DIMENSION),
            ],
        ];
    }

    /**
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();

            if ($validator->errors()->has('dev_null_username')
                || $this->input('dev_null_username') !== null
                || ! $user instanceof User) {
                return;
            }

            if (DevNullUsername::isClaimedByAnotherAccount(DevNullUsername::normalize($user->handle), $user)) {
                $validator->errors()->add('dev_null_username', 'Your GitHub username is already claimed by another account.');
            }
        }];
    }

    /**
     * @return array{
     *     dev_null_username: string|null,
     *     name: string,
     *     bio: string|null,
     *     company: string|null,
     *     location: string|null,
     *     website: string|null,
     *     sponsors_url: string|null
     * }
     */
    public function profileAttributes(): array
    {
        return [
            'dev_null_username' => $this->validatedNullableString('dev_null_username'),
            'name' => $this->validatedString('name'),
            'bio' => $this->validatedNullableString('bio'),
            'company' => $this->validatedNullableString('company'),
            'location' => $this->validatedNullableString('location'),
            'website' => $this->validatedNullableString('website'),
            'sponsors_url' => $this->validatedNullableString('sponsors_url'),
        ];
    }

    public function avatar(): ?UploadedFile
    {
        $avatar = $this->file('avatar');

        return $avatar instanceof UploadedFile ? $avatar : null;
    }

    protected function prepareForValidation(): void
    {
        $username = $this->input('dev_null_username');

        if (is_string($username)) {
            $this->merge(['dev_null_username' => DevNullUsername::normalize($username)]);
        }
    }

    private function validatedString(string $key): string
    {
        $value = $this->validated($key);

        if (! is_string($value)) {
            throw new LogicException("Validated profile field [{$key}] must be a string.");
        }

        return $value;
    }

    private function validatedNullableString(string $key): ?string
    {
        $value = $this->validated($key);

        if ($value !== null && ! is_string($value)) {
            throw new LogicException("Validated profile field [{$key}] must be a string or null.");
        }

        return $value;
    }
}
