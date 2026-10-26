<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mail\AdminEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final readonly class CreateTestEmail
{
    /**
     * Sends the email right away to one address, marked as a test, with the admin's own unsubscribe link in it.
     */
    public function handle(User $admin, string $address, string $subject, string $body): void
    {
        Mail::sendNow(new AdminEmail($admin, "[Test] {$subject}", $body)->to($address));
    }
}
