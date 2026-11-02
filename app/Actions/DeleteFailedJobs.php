<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Queue\Failed\FailedJobProviderInterface;

final readonly class DeleteFailedJobs
{
    public function __construct(private FailedJobProviderInterface $failer)
    {
        //
    }

    public function handle(): void
    {
        $this->failer->flush();
    }
}
