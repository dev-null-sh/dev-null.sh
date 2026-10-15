<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Http\UploadedFile;

final readonly class NewEvent
{
    public function __construct(
        public string $title,
        public EventDetails $details,
        public ?UploadedFile $cover,
    ) {
        //
    }
}
