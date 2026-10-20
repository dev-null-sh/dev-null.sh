<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cli;

use App\Actions\CreateCommit;
use App\Actions\CreateScheduledCommit;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCliCommitRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class CommitController
{
    public function store(CreateCliCommitRequest $request, #[CurrentUser] User $user, CreateCommit $action, CreateScheduledCommit $schedule): Response
    {
        if ($request->filled('scheduled_at')) {
            $schedule->handle($user, $request->string('body')->value(), CarbonImmutable::parse($request->string('scheduled_at')->value()), $request->images());

            return response('Scheduled for '.$request->string('scheduled_at')->value().': '.route('scheduled-commit.index')."\n", 201)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $commit = $action->handle($user, $request->string('body')->value(), $request->parent(), images: $request->images());

        $url = route('commit.show', [$user, $commit]);

        $message = $commit->status === CommitStatus::Pending
            ? "Committed: {$url} (pending review, only you can see it until it is approved)"
            : "Committed: {$url}";

        return response($message."\n", 201)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
