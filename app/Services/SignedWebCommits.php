<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Passkey;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * @phpstan-type Proof array{passkey_name: string, credential_id: string, public_key: string, algorithm: int, nonce: string, statement: string, client_data_json: string, authenticator_data: string, signature: string, signed_at: string}
 */
final class SignedWebCommits
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'signature' => ['nullable', 'array:nonce,credential_id,client_data_json,authenticator_data,signature', 'required_array_keys:nonce,credential_id,client_data_json,authenticator_data,signature'],
            'signature.*' => ['string', 'max:8192'],
        ];
    }

    /**
     * @return Closure(Validator): void
     */
    public static function check(FormRequest $request, ?string $parentId, ?string $scheduledAt = null): Closure
    {
        return function (Validator $validator) use ($request, $parentId, $scheduledAt): void {
            $user = $request->user();

            if (! $user instanceof User || $validator->errors()->isNotEmpty()) {
                return;
            }

            $images = $request->file('images');
            $statement = self::statement($request, $parentId, $scheduledAt, array_values(array_map(
                fn (UploadedFile $image): string => (string) hash_file('sha256', $image->getRealPath()),
                is_array($images) ? $images : [],
            )));
            $signature = $request->input('signature');
            $passkey = self::verify($validator, $user, $signature, $statement, 'commit');

            if ($passkey instanceof Passkey && is_array($signature)) {
                /** @var array{nonce: string, credential_id: string, client_data_json: string, authenticator_data: string, signature: string} $signature */
                $request->attributes->set('signature-proof', [
                    'passkey_name' => $passkey->name,
                    'credential_id' => $passkey->credential_id,
                    'public_key' => $passkey->credential['public_key'],
                    'algorithm' => $passkey->credential['algorithm'],
                    'nonce' => $signature['nonce'],
                    'statement' => $statement,
                    'client_data_json' => $signature['client_data_json'],
                    'authenticator_data' => $signature['authenticator_data'],
                    'signature' => $signature['signature'],
                    'signed_at' => now()->toIso8601String(),
                ]);
            }
        };
    }

    /**
     * @return Closure(Validator): void
     */
    public static function checkIssue(FormRequest $request): Closure
    {
        return function (Validator $validator) use ($request): void {
            $user = $request->user();

            if (! $user instanceof User || $validator->errors()->isNotEmpty()) {
                return;
            }

            self::verify($validator, $user, $request->input('signature'), PasskeyChallenges::gitHubIssue($request->string('command')->value(), $request->string('body')->value()), 'issue');
        };
    }

    /**
     * @return Proof|null
     */
    public static function proof(FormRequest $request): ?array
    {
        /** @var Proof|null */
        return $request->attributes->get('signature-proof');
    }

    /**
     * The terminal and the app can't sign with a passkey, so they can't post for an account that requires it.
     *
     * @return Closure(Validator): void
     */
    public static function refuse(FormRequest $request): Closure
    {
        return function (Validator $validator) use ($request): void {
            $user = $request->user();

            if ($user instanceof User && $user->requires_signed_commits) {
                $validator->errors()->add('body', 'Your account requires commits signed with a passkey, so it only takes commits from the web.');
            }
        };
    }

    /**
     * @param  list<string>  $imageHashes
     */
    public static function statement(FormRequest $request, ?string $parentId, ?string $scheduledAt, array $imageHashes): string
    {
        /** @var list<string> $options */
        $options = $request->array('poll.options');

        return PasskeyChallenges::commit(
            $parentId,
            $request->filled('quoted_commit_id') ? $request->string('quoted_commit_id')->value() : null,
            $scheduledAt,
            $request->filled('poll') ? ['options' => array_map(strval(...), $options), 'duration' => $request->integer('poll.duration')] : null,
            $imageHashes,
            $request->string('body')->value(),
        );
    }

    private static function verify(Validator $validator, User $user, mixed $signature, string $statement, string $subject): ?Passkey
    {
        if (! is_array($signature)) {
            if ($user->requires_signed_commits) {
                $validator->errors()->add('signature', "Your account requires signed commits. Sign this {$subject} with your passkey.");
            }

            return null;
        }

        try {
            /** @var array{nonce: string, credential_id: string, client_data_json: string, authenticator_data: string, signature: string} $signature */
            return resolve(PasskeyChallenges::class)->verify($user, $signature, $statement);
        } catch (InvalidArgumentException $invalidArgumentException) {
            $validator->errors()->add('signature', $invalidArgumentException->getMessage());

            return null;
        }
    }
}
