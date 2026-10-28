<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\JoinLinkEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final readonly class CreateJoinLink
{
    /**
     * Each address gets the email once a month at most, so the form can't fill someone's inbox. Only a keyed hash of it
     * is kept to know that, and it expires with the month. The link starts from the configured URL, never from the request's
     * host, and its fragment tells the page it was opened from the email without reaching the server.
     */
    public function handle(string $email, string $path): void
    {
        $hash = hash_hmac('sha256', Str::lower($email), config()->string('app.key'));

        if (! Cache::add("join-link:{$hash}", true, now()->addDays(30))) {
            return;
        }

        Mail::to($email)->queue(new JoinLinkEmail(mb_rtrim(config()->string('app.url'), '/').$path.'#join-link'));
    }
}
