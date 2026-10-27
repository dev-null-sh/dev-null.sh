<?php

declare(strict_types=1);

use App\Actions\CreateTomFriendship;
use App\Mail\AdminEmail;
use App\Models\User;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('shows admins the composer with everyone who can be emailed', function (): void {
    $admin = User::factory()->create(['handle' => 'admin', 'email' => 'admin@example.com']);
    User::factory()->create();
    User::factory()->create(['email' => null]);
    User::factory()->create(['emails_unsubscribed_at' => now()]);
    User::factory()->banned()->create();
    User::factory()->create(['github_id' => CreateTomFriendship::GITHUB_ID]);

    $this->actingAs($admin)
        ->get(route('email.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('operations/emails')
            ->where('activity', 'anyone')
            ->where('signup', 'anytime')
            ->where('drafts', ['weekly', 'monthly'])
            ->where('recipients', 2)
            ->where('testAddress', 'admin@example.com'));
});

it('narrows the audience down by when people were last seen and signed up', function (string $activity, string $signup, int $recipients): void {
    $this->travelTo('2026-10-24 12:00:00');
    $admin = User::factory()->create(['handle' => 'admin', 'last_seen_at' => today(), 'created_at' => now()->subYear()]);
    User::factory()->create(['last_seen_at' => today()->subDays(3), 'created_at' => now()->subDays(3)]);
    User::factory()->create(['last_seen_at' => today()->subDays(45), 'created_at' => now()->subDays(60)]);
    User::factory()->create(['last_seen_at' => null, 'created_at' => now()->subYear()]);

    $this->actingAs($admin)
        ->get(route('email.create', ['activity' => $activity, 'signup' => $signup]))
        ->assertInertia(fn ($page) => $page
            ->where('activity', $activity)
            ->where('signup', $signup)
            ->where('recipients', $recipients));
})->with([
    'seen this week' => ['active-7d', 'anytime', 2],
    'seen this quarter' => ['active-90d', 'anytime', 3],
    'not seen for a month' => ['inactive-30d', 'anytime', 2],
    'not seen for a quarter' => ['inactive-90d', 'anytime', 1],
    'signed up this week' => ['anyone', '7d', 1],
    'signed up this quarter and not seen for a month' => ['inactive-30d', '90d', 1],
]);

it('queues the email for everyone in the audience, one each', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['handle' => 'admin', 'last_seen_at' => today()]);
    $recent = User::factory()->create(['last_seen_at' => today()]);
    User::factory()->create(['last_seen_at' => today()->subYear()]);
    User::factory()->create(['last_seen_at' => today(), 'emails_unsubscribed_at' => now()]);

    $this->actingAs($admin)
        ->fromRoute('email.create')
        ->post(route('email.store'), [
            'subject' => 'What shipped',
            'body' => '## New\n\n- Emails',
            'activity' => 'active-7d',
            'signup' => 'anytime',
        ])
        ->assertRedirectToRoute('email.create')
        ->assertInertiaFlash('toast.message', 'Queued the email for 2 people.');

    Mail::assertQueuedCount(2);
    Mail::assertQueued(AdminEmail::class, fn (AdminEmail $mail): bool => $mail->hasTo($recent->email) && $mail->hasSubject('What shipped'));
    Mail::assertQueued(AdminEmail::class, fn (AdminEmail $mail): bool => $mail->hasTo($admin->email));
});

it('requires a subject, a body, and an audience', function (): void {
    Mail::fake();

    $this->actingAs(User::factory()->create(['handle' => 'admin']))
        ->post(route('email.store'), ['activity' => 'everyone'])
        ->assertSessionHasErrors(['subject', 'body', 'activity', 'signup']);

    Mail::assertNothingQueued();
});

it('hides the composer from non admins', function (): void {
    Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('email.create'))->assertNotFound();
    $this->actingAs($user)->post(route('email.store'), [
        'subject' => 'Hi',
        'body' => 'Hi',
        'activity' => 'anyone',
        'signup' => 'anytime',
    ])->assertNotFound();

    Mail::assertNothingQueued();
});

it('queues only the user id, and reads their address and unsubscribe link when it is sent', function (): void {
    $user = User::factory()->create(['email' => 'dev@example.com']);
    $mail = new AdminEmail($user, 'What shipped', 'Emails.');
    $url = URL::signedRoute('unsubscription.show', $user);

    expect(serialize($mail))->not->toContain('dev@example.com')
        ->and($mail->hasTo('dev@example.com'))->toBeTrue()
        ->and($mail->headers()->text)->toBe(['List-Unsubscribe' => "<{$url}>", 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'])
        ->and($mail->render())->toContain(e($url));
});

it('skips a queued email for someone who unsubscribed or was banned after it was queued', function (array $since): void {
    config(['mail.default' => 'array']);
    $user = User::factory()->create(['email' => 'dev@example.com']);
    $queued = serialize(new AdminEmail($user, 'What shipped', 'Emails.'));
    $user->forceFill($since)->save();

    unserialize($queued)->send(resolve(MailFactory::class));

    expect(resolve(MailFactory::class)->mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);
})->with([
    'unsubscribed' => [['emails_unsubscribed_at' => now()]],
    'banned' => [['banned_at' => now()]],
]);

it('still sends a queued email to someone who is subscribed, and a test email to the address the admin chose', function (): void {
    config(['mail.default' => 'array']);
    $user = User::factory()->create(['email' => 'dev@example.com']);
    $admin = User::factory()->create(['email' => 'admin@example.com', 'emails_unsubscribed_at' => now()]);

    unserialize(serialize(new AdminEmail($user, 'What shipped', 'Emails.')))->send(resolve(MailFactory::class));
    new AdminEmail($admin, '[Test] What shipped', 'Emails.')->to('test@example.com')->send(resolve(MailFactory::class));

    $sent = resolve(MailFactory::class)->mailer('array')->getSymfonyTransport()->messages();

    expect($sent)->toHaveCount(2)
        ->and($sent[0]->getEnvelope()->getRecipients()[0]->getAddress())->toBe('dev@example.com')
        ->and($sent[1]->getEnvelope()->getRecipients()[0]->getAddress())->toBe('test@example.com');
});
