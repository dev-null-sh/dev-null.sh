<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\ChildSafetyReportEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final readonly class CreateChildSafetyAlert
{
    /**
     * Emails every moderator a link to the queue the report waits in, and nothing about the report. The report is
     * already saved, so a mail that can't be queued is logged instead of failing the person who reported.
     */
    public function handle(string $queueUrl): void
    {
        $moderators = User::query()
            ->whereHas('profile', fn (Builder $query): Builder => $query->whereIn('handle', config()->array('app.admins')))
            ->whereNotNull('email')
            ->get();

        if ($moderators->isEmpty()) {
            Log::warning('No moderator has an email address to be alerted about a child safety report.');
        }

        $moderators->each(fn (User $moderator): mixed => rescue(fn (): mixed => Mail::send(new ChildSafetyReportEmail($moderator, $queueUrl))));
    }
}
