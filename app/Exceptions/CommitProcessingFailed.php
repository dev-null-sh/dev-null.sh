<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class CommitProcessingFailed extends RuntimeException
{
    /**
     * @param  string|null  $bodyHash  The SHA-256 of the body being processed, when the failure is about that body.
     */
    public function __construct(string $operation, string $commitId, Throwable $exception, public readonly ?string $reason = null, public readonly ?string $bodyHash = null)
    {
        parent::__construct(
            $operation.' failed for commit '.$commitId.' ('.$exception::class.', code '.$exception->getCode().').'
                .($reason === null ? '' : ' Reason: '.$reason.'.'),
            (int) $exception->getCode(),
        );
    }
}
