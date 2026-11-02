<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\EmailActivity;
use App\Enums\EmailSignup;
use App\Mail\AdminEmail;
use App\Models\User;
use App\Queries\EmailRecipientsQuery;
use Illuminate\Support\Facades\Mail;

final readonly class CreateEmail
{
    public function __construct(private EmailRecipientsQuery $recipients)
    {
        //
    }

    /**
     * Queues the email for everyone it reaches, one each, and returns how many that is.
     */
    public function handle(string $subject, string $body, EmailActivity $activity, EmailSignup $signup): int
    {
        $count = 0;

        $this->recipients->builder($activity, $signup)->lazyById(500)->each(function (User $user) use ($subject, $body, &$count): void {
            Mail::send(new AdminEmail($user, $subject, $body));

            $count++;
        });

        return $count;
    }
}
