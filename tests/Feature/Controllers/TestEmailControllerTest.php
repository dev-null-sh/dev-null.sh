<?php

declare(strict_types=1);

use App\Mail\AdminEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('sends admins a test email right away, marked as a test', function (): void {
    Mail::fake();

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->fromRoute('email.create')
        ->post(route('test-email.store'), [
            'address' => 'someone@example.com',
            'subject' => 'What shipped',
            'body' => 'Emails, **finally**.',
        ])
        ->assertRedirectToRoute('email.create')
        ->assertInertiaFlash('toast.message', 'Sent a test email to someone@example.com.');

    Mail::assertSent(AdminEmail::class, fn (AdminEmail $mail): bool => $mail->hasTo('someone@example.com')
        && $mail->hasSubject('[Test] What shipped'));
});

it('requires an email address', function (): void {
    Mail::fake();

    $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->post(route('test-email.store'), ['address' => 'nope', 'subject' => 'Hi', 'body' => 'Hi'])
        ->assertSessionHasErrors('address');

    Mail::assertNothingSent();
});

it('does not let non admins send test emails', function (): void {
    Mail::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('test-email.store'), ['address' => 'someone@example.com', 'subject' => 'Hi', 'body' => 'Hi'])
        ->assertNotFound();

    Mail::assertNothingSent();
});
