<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Passkey;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * A passkey signs SHA-256("{nonce}\n{statement}") instead of a random challenge, so the signature covers what the server
 * will act on. The server builds the statement twice, from the draft and from the posted request, and never trusts the
 * browser's copy.
 */
final readonly class PasskeyChallenges
{
    private const int TIMEOUT_SECONDS = 300;

    public function __construct(private Passkeys $passkeys)
    {
        //
    }

    /**
     * Image descriptions and the choice of link card are left out.
     *
     * @param  array{options: list<string>, duration: int}|null  $poll
     * @param  list<string>  $imageHashes
     */
    public static function commit(?string $parentId, ?string $quotedCommitId, ?string $scheduledAt, ?array $poll, array $imageHashes, string $body): string
    {
        return json_encode([
            'devnull-web-commit',
            $parentId,
            $quotedCommitId,
            $scheduledAt,
            $poll,
            $imageHashes,
            str_replace("\r\n", "\n", $body),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function gitHubIssue(string $command, string $body): string
    {
        return json_encode(['devnull-issue', $command, str_replace("\r\n", "\n", $body)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function settings(string $action): string
    {
        return "devnull-signing-settings\n{$action}";
    }

    /**
     * @return array{nonce: string, options: array<string, mixed>}
     */
    public function issue(User $user, string $statement): array
    {
        $nonce = bin2hex(random_bytes(16));
        Cache::put("passkey-challenge:{$nonce}", $user->id, self::TIMEOUT_SECONDS);

        return [
            'nonce' => $nonce,
            'options' => $this->passkeys->requestOptions($user, $this->challenge($nonce, $statement)),
        ];
    }

    /**
     * @param  array{nonce: string, credential_id: string, client_data_json: string, authenticator_data: string, signature: string}  $signature
     */
    public function verify(User $user, array $signature, string $statement): Passkey
    {
        throw_unless(
            preg_match('/^[0-9a-f]{32}$/', $signature['nonce']) === 1
                && Cache::add("passkey-challenge-spent:{$signature['nonce']}", true, self::TIMEOUT_SECONDS)
                && Cache::pull("passkey-challenge:{$signature['nonce']}") === $user->id,
            InvalidArgumentException::class,
            'The signature expired or was already used. Try again.',
        );

        return $this->passkeys->verify($user, $signature, $this->challenge($signature['nonce'], $statement));
    }

    /**
     * @return Closure(Validator): void
     */
    public function confirmation(FormRequest $request, User $user, string $action): Closure
    {
        return function (Validator $validator) use ($request, $user, $action): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var array{nonce: string, credential_id: string, client_data_json: string, authenticator_data: string, signature: string}|null $signature */
            $signature = $request->input('signature');

            try {
                throw_unless(is_array($signature), InvalidArgumentException::class, 'Confirm this change with your passkey.');

                $this->verify($user, $signature, self::settings($action));
            } catch (InvalidArgumentException $invalidArgumentException) {
                $validator->errors()->add('signature', $invalidArgumentException->getMessage());
            }
        };
    }

    public function challenge(string $nonce, string $statement): string
    {
        return Passkeys::encode(hash('sha256', "{$nonce}\n{$statement}", true));
    }
}
