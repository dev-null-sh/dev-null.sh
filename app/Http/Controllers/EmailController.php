<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateEmail;
use App\Enums\EmailActivity;
use App\Enums\EmailDraft;
use App\Enums\EmailSignup;
use App\Http\Requests\CreateEmailRequest;
use App\Http\Requests\ShowEmailFormRequest;
use App\Models\User;
use App\Queries\EmailRecipientsQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EmailController
{
    public function create(ShowEmailFormRequest $request, #[CurrentUser] User $user, EmailRecipientsQuery $recipients): Response
    {
        return Inertia::render('operations/emails', [
            'activity' => $request->activity()->value,
            'signup' => $request->signup()->value,
            'activities' => array_column(EmailActivity::cases(), 'value'),
            'signups' => array_column(EmailSignup::cases(), 'value'),
            'drafts' => array_column(EmailDraft::cases(), 'value'),
            'recipients' => $recipients->builder($request->activity(), $request->signup())->count(),
            'testAddress' => $user->email,
        ]);
    }

    public function store(CreateEmailRequest $request, CreateEmail $action): RedirectResponse
    {
        $count = $action->handle(
            $request->string('subject')->value(),
            $request->string('body')->value(),
            $request->activity(),
            $request->signup(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice('Queued the email for :count person.|Queued the email for :count people.', $count),
        ]);

        return back();
    }
}
