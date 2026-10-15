<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowEventCalendarRequest;
use App\Models\Commit;
use App\Services\EventCalendar;
use Illuminate\Http\Response;

final readonly class EventCalendarController
{
    /**
     * Opened in place (`?open=1`), Safari and iOS hand the file straight to Calendar; otherwise it downloads.
     */
    public function show(ShowEventCalendarRequest $request, Commit $commit, EventCalendar $calendar): Response
    {
        return response($calendar->render($request->event()), headers: [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => ($request->boolean('open') ? 'inline' : 'attachment').'; filename="'.$commit->shortHash().'.ics"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
