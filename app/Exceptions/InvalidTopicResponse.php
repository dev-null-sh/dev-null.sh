<?php

declare(strict_types=1);

namespace App\Exceptions;

use UnexpectedValueException;

final class InvalidTopicResponse extends UnexpectedValueException
{
    public static function decision(): self
    {
        return new self('The topic classifier returned no valid decision.');
    }
}
