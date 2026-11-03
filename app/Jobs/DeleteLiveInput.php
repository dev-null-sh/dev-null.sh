<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\CloudflareStream;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

#[Backoff(60)]
#[Tries(5)]
final class DeleteLiveInput implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $uid)
    {
        //
    }

    public function handle(CloudflareStream $stream): void
    {
        $stream->deleteLiveInput($this->uid);
    }
}
