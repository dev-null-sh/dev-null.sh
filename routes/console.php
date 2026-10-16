<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('commits:submit-scheduled')->everyMinute()->withoutOverlapping(5);
