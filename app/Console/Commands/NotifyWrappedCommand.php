<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateWrappedNotifications;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('wrapped:notify {--year= : The year to wrap, defaults to the current year}')]
#[Description('Tell every handle with a commit that year that their Wrapped is ready')]
final class NotifyWrappedCommand extends Command
{
    public function handle(CreateWrappedNotifications $createWrappedNotifications): int
    {
        $year = (int) ($this->option('year') ?? date('Y'));

        $count = $createWrappedNotifications->handle($year);

        $this->components->info("Wrapped notifications queued for {$count} handles for {$year}.");

        return self::SUCCESS;
    }
}
