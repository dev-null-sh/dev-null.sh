<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class HeadlineSummaryFailed extends RuntimeException
{
    public function __construct(string $headlineId, Throwable $exception)
    {
        parent::__construct('Headline summary failed for headline '.$headlineId.' ('.$exception::class.').');
    }
}
