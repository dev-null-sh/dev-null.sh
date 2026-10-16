<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Queue\Failed\FailedJobProviderInterface;

final readonly class DeleteFailedJob
{
    public function __construct(private FailedJobProviderInterface $failer)
    {
        //
    }

    public function handle(string $uuid): void
    {
        $this->failer->forget($uuid);
    }
}
