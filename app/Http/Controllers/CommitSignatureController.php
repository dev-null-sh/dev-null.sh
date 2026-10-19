<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowCommitSignatureRequest;
use App\Models\Commit;
use App\Services\PasskeyChallenges;
use App\Services\Passkeys;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final readonly class CommitSignatureController
{
    /**
     * To check it without /dev/null: the challenge is SHA-256("{nonce}\n{statement}"), and the passkey signed
     * authenticator_data || SHA-256(client_data_json).
     */
    public function show(ShowCommitSignatureRequest $request, Commit $commit, PasskeyChallenges $challenges, Passkeys $passkeys): JsonResponse
    {
        $proof = $commit->signature;
        assert($proof !== null);

        $challenge = $challenges->challenge($proof['nonce'], $proof['statement']);

        try {
            $passkeys->check($proof['public_key'], $proof, $challenge);
            $verified = true;
        } catch (InvalidArgumentException) {
            $verified = false;
        }

        return response()->json([
            'commit_id' => $commit->id,
            'signed_at' => $proof['signed_at'],
            'verified' => $verified,
            'passkey' => [
                'name' => $proof['passkey_name'],
                'credential_id' => $proof['credential_id'],
                'algorithm' => $proof['algorithm'] === Passkeys::ES256 ? 'ES256' : 'RS256',
                'public_key' => $proof['public_key'],
            ],
            'nonce' => $proof['nonce'],
            'statement' => $proof['statement'],
            'challenge' => $challenge,
            'client_data' => json_decode(Passkeys::decode($proof['client_data_json']), true),
            'client_data_json' => $proof['client_data_json'],
            'authenticator_data' => $proof['authenticator_data'],
            'signature' => $proof['signature'],
        ], options: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
