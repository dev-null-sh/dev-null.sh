<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An error from the signed API. It answers with its message and its code, and the status that goes with the code.
 */
final class ApiException extends HttpException
{
    /**
     * @param  array<mixed>  $headers
     */
    public function __construct(public readonly ApiErrorCode $errorCode, string $message, array $headers = [])
    {
        parent::__construct($errorCode->status(), $message, headers: $headers);
    }

    /**
     * A profile across a block is unavailable to a signed request, as on the web. Only the one who blocked the other
     * is told why.
     */
    public static function acrossBlock(User $user, bool $blockedByViewer): self
    {
        return $blockedByViewer
            ? new self(ApiErrorCode::UserBlocked, __("You blocked :name. Neither of you can follow, reply to, or star the other, or see each other's commits.", [
                'name' => $user->name,
            ]))
            : new self(ApiErrorCode::UserUnavailable, __("This profile isn't available."));
    }

    /**
     * A commit that doesn't exist, was deleted, isn't published, or that the user may not see. The web answers them
     * all alike, so nothing tells them apart.
     */
    public static function commitUnavailable(): self
    {
        return new self(ApiErrorCode::CommitUnavailable, __("This commit isn't available."));
    }

    /**
     * The one answer to a code the app can't redeem, whatever is wrong with it, as OAuth answers it.
     */
    public static function invalidGrant(): self
    {
        return new self(ApiErrorCode::InvalidGrant, __('This sign-in has expired or was already used. Sign in again in the app.'));
    }

    /**
     * The user gave every pastel they get today. The web's own words, which say when they come back.
     */
    public static function pasteisExhausted(): self
    {
        return new self(ApiErrorCode::PasteisExhausted, __('you ran out of pastéis. wait until tomorrow.'));
    }

    /**
     * The profile could not be mirrored from GitHub again, as the web says it.
     */
    public static function gitHubSyncFailed(): self
    {
        return new self(ApiErrorCode::GitHubSyncFailed, __('Could not sync from GitHub. Try again in a minute.'));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode->value,
        ], $this->getStatusCode(), $this->getHeaders());
    }
}
