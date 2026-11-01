<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a moderator a child safety report is waiting. It links to the queue and says nothing else: not who reported,
 * who was reported, or what the report says. Only the moderator's id is queued.
 */
#[Backoff([60, 600])]
#[Tries(3)]
#[DeleteWhenMissingModels]
final class ChildSafetyReportEmail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly User $moderator, private readonly string $queueUrl)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(to: [(string) $this->moderator->email], subject: 'A child safety report needs review');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.child-safety-report', with: ['url' => $this->queueUrl]);
    }
}
