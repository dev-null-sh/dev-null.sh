<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\JoinLinkEmail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

final readonly class CreateJoinLink
{
    /**
     * Each address gets the email once a month at most, so the form can't fill someone's inbox. Only a keyed hash of it
     * is kept to know that, and it expires with the month.
     */
    public function handle(string $email, string $path): void
    {
        $hash = hash_hmac('sha256', Str::lower($email), config()->string('app.key'));

        if (! Cache::add("join-link:{$hash}", true, now()->addDays(30))) {
            return;
        }

        Mail::to($email)->queue(new JoinLinkEmail(Uri::of(url($path))->withQuery(['ref' => 'join-link'])->value()));
    }
}
