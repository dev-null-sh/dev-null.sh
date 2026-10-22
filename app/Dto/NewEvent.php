<?php

declare(strict_types=1);

namespace App\Dto;

use Carbon\CarbonImmutable;

final readonly class NewEvent
{
    /**
     * @param  list<array{label: string, starts_at: CarbonImmutable}>  $agenda
     */
    public function __construct(
        public string $title,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?string $location,
        public ?string $url,
        public array $agenda = [],
    ) {
        //
    }
}
