<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateHeadlineSummary;
use App\Exceptions\HeadlineSummaryFailed;
use App\Models\Headline;
use App\Queries\HotHeadlinesQuery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('headlines:refresh')]
#[Description('Immediately regenerate recaps for current hot topics, including still-fresh recaps')]
final class RefreshHeadlineSummariesCommand extends Command
{
    public function handle(HotHeadlinesQuery $hotHeadlines, CreateHeadlineSummary $action): int
    {
        $headlines = Headline::query()->whereIn('name', array_column($hotHeadlines->get(), 'name'))->get(['id']);

        foreach ($headlines as $headline) {
            try {
                $action->handle($headline->id, refresh: true);
            } catch (Throwable $exception) {
                throw new HeadlineSummaryFailed($headline->id, $exception);
            }
        }

        $this->components->info("Processed {$headlines->count()} hot topics for recap refresh.");

        return self::SUCCESS;
    }
}
