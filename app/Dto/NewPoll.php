<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\PollDuration;

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
        //
    }
}
