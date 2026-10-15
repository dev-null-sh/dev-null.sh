<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\PollDuration;
use InvalidArgumentException;

final readonly class NewPoll
{
    public const int MinOptions = 2;

    public const int MaxOptions = 4;

    public const int MaxOptionLength = 50;

    /**
     * @param  list<string>  $options
     */
    public function __construct(
        public array $options,
        public PollDuration $duration,
    ) {
        throw_if(count($options) < self::MinOptions || count($options) > self::MaxOptions, InvalidArgumentException::class, 'A poll needs between 2 and 4 options.');
    }
}
