<?php

declare(strict_types=1);

namespace App\Dto;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Where a user's streaming software sends their stream. Only its owner ever sees the key.
 *
 * @implements Arrayable<string, string>
 */
final readonly class LiveInput implements Arrayable
{
    public function __construct(
        public string $rtmpsUrl,
        public string $streamKey,
    ) {
        //
    }

    /**
     * @return array{rtmps_url: string, stream_key: string}
     */
    public function toArray(): array
    {
        return [
            'rtmps_url' => $this->rtmpsUrl,
            'stream_key' => $this->streamKey,
        ];
    }
}
