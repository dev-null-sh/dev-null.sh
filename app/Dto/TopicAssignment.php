<?php

declare(strict_types=1);

namespace App\Dto;

use App\Models\Commit;

final readonly class TopicAssignment
{
    public function __construct(public Commit $commit, public string $value)
    {
        //
    }

    /** @return array{label: string, body: string} */
    public function descriptor(): array
    {
        return ['label' => $this->value, 'body' => $this->commit->body];
    }

    public function withValue(string $value): self
    {
        return new self($this->commit, $value);
    }
}
