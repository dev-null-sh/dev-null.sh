<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cli;

use App\Actions\CreateCommit;
use App\Actions\FindCommitByReference;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

final readonly class CommitController
{
    public function store(CreateCommitRequest $request, #[CurrentUser] User $user, CreateCommit $action, FindCommitByReference $findCommitByReference): Response
    {
        $parent = $request->filled('reply_to') ? $findCommitByReference->handle($user, $request->string('reply_to')->value()) : null;

        $commit = $action->handle($user, $request->string('body')->value(), $parent);

        $url = route('commit.show', [$user, $commit]);

        $message = $commit->status === CommitStatus::Pending
            ? __('Committed: :url (pending review, only you can see it until it is approved)', ['url' => $url])
            : __('Committed: :url', ['url' => $url]);

        return response($message."\n", 201)->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
