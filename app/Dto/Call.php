<?php

declare(strict_types=1);

namespace App\Dto;

use App\Actions\CreateConversationCall;
use App\Models\User;

/**
 * A one-to-one call while it rings: the caller's offer, the callee's answer once they accept, and when it started
 * ringing. It only lives in the cache, encrypted, since the offer and answer hold the browsers' addresses.
 */
final readonly class Call
{
    public function __construct(
        public string $callerId,
        public string $offer,
        public ?string $answer,
        public int $rangAt,
    ) {
        //
    }

    /**
     * @param  array<mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $attributes = fluent($attributes);

        return new self(
            $attributes->string('caller_id')->value(),
            $attributes->string('offer')->value(),
            $attributes->filled('answer') ? $attributes->string('answer')->value() : null,
            $attributes->integer('rang_at', now()->getTimestamp()),
        );
    }

    public function isFrom(User $caller): bool
    {
        return $this->callerId === $caller->id;
    }

    public function isAnswered(): bool
    {
        return $this->answer !== null;
    }

    /**
     * Whether it rang for about as long as a call rings, so giving up on it means nobody answered.
     */
    public function hasRungOut(): bool
    {
        return now()->getTimestamp() - $this->rangAt >= CreateConversationCall::SECONDS - 5;
    }

    public function answered(string $answer): self
    {
        return new self($this->callerId, $this->offer, $answer, $this->rangAt);
    }

    /**
     * @return array{caller_id: string, offer: string, answer: string|null, rang_at: int}
     */
    public function toArray(): array
    {
        return [
            'caller_id' => $this->callerId,
            'offer' => $this->offer,
            'answer' => $this->answer,
            'rang_at' => $this->rangAt,
        ];
    }
}
