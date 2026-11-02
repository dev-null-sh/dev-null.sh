<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

/**
 * The link a guest asked for on their phone, to join from their computer, back to the page they were reading. Encrypted
 * while it waits in the queue, or in the failed jobs, since it carries the guest's address.
 */
#[Backoff([60, 600])]
#[Tries(3)]
final class JoinLinkEmail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $url)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your link to /dev/null');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.join-link', with: ['url' => $this->url]);
    }
}
