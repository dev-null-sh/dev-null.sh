<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class CommitProcessingFailed extends RuntimeException
{
    public function __construct(string $operation, string $commitId, Throwable $exception)
    {
        parent::__construct(
            $operation.' failed for commit '.$commitId.' ('.$exception::class.', code '.$exception->getCode().').',
            $exception->getCode(),
        );
    }
}
