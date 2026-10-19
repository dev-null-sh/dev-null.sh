<?php

declare(strict_types=1);

use App\Jobs\ClearStaleTagVerifications;
use App\Jobs\EmbedKnowledgeBase;
use App\Jobs\RefreshTagSiteMeta;
use Illuminate\Support\Facades\Schedule;

Schedule::command('commits:submit-scheduled')->everyMinute()->withoutOverlapping(5);
Schedule::job(new EmbedKnowledgeBase())->hourly();
Schedule::job(new ClearStaleTagVerifications())->daily();
Schedule::job(new RefreshTagSiteMeta())->weekly();
