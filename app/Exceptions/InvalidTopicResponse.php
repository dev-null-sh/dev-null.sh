<?php

declare(strict_types=1);

namespace App\Exceptions;

use UnexpectedValueException;

final class InvalidTopicResponse extends UnexpectedValueException
{
    public static function labels(): self
    {
        return new self('Invalid topic labels.');
    }

    public static function label(): self
    {
        return new self('Invalid topic label.');
    }

    public static function decision(): self
    {
        return new self('The topic classifier returned no valid decision.');
    }
}
