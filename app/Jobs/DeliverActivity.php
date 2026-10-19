<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\ActivityStreams;
use App\Services\HttpSignatures;
use App\Services\PublicAddresses;
use App\Services\RemoteActors;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Http;
use Uri\WhatWg\Url;

#[Backoff([60, 300, 1800, 7200])]
#[Tries(5)]
#[DeleteWhenMissingModels]
final class DeliverActivity implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $activity
     */
    public function __construct(
        public readonly User $user,
        public readonly string $inbox,
        public readonly array $activity,
    ) {
        //
    }

    /**
     * Server errors, rate limits and connection failures are thrown, so the delivery is tried again later. Any other
     * refusal is final.
     */
    public function handle(HttpSignatures $signatures, ActivityStreams $streams, RemoteActors $actors, PublicAddresses $publicAddresses): void
    {
        $inbox = $actors->allowedUrl($this->inbox);
        $address = $inbox instanceof Url ? $publicAddresses->of($inbox, $actors->allowsPrivateAddresses()) : null;

        if ($address === null || $this->user->private_key === null) {
            return;
        }

        $body = json_encode($this->activity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $response = Http::withHeaders($signatures->sign($inbox->toAsciiString(), $body, $streams->keyId($this->user), $this->user->private_key))
            ->withBody($body, ActivityStreams::CONTENT_TYPE)
            ->connectTimeout(3)
            ->timeout(10)
            ->withoutRedirecting()
            ->withOptions(['curl' => $publicAddresses->pin($inbox, $address)])
            ->post($inbox->toAsciiString());

        if ($response->serverError() || $response->tooManyRequests()) {
            $response->throw();
        }
    }
}
