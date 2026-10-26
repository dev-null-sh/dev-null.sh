<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * An email the admins write to the people on /dev/null, in Markdown, with a link to unsubscribe that mail providers
 * also offer as a one-click button. Only the user's id is queued, so their address is read when the email is sent,
 * and is never kept in a job payload.
 */
#[Backoff([60, 600])]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class AdminEmail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly User $user, private readonly string $title, private readonly string $body)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(to: $this->to === [] ? [(string) $this->user->email] : [], subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin-email', with: [
            'body' => $this->body,
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ]);
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->unsubscribeUrl()}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('unsubscription.show', $this->user);
    }
}
