<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\Commit;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class ThreadContinuation implements Arrayable
{
    /**
     * @param  Collection<int, Commit>  $commits
     */
    public function __construct(
        public Collection $commits,
        public int $position,
        public int $length,
        public bool $isCapped,
        public ?string $nextId,
    ) {
        //
    }

    /**
     * @return array{data: array<int, mixed>, position: int, length: int, is_capped: bool, next_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'data' => $this->commits->map(fn (Commit $commit): array => $commit->toArray())->all(),
            'position' => $this->position,
            'length' => $this->length,
            'is_capped' => $this->isCapped,
            'next_id' => $this->nextId,
        ];
    }
}
