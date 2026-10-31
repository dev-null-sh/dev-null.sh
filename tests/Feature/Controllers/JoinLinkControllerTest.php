<?php

declare(strict_types=1);

use App\Mail\JoinLinkEmail;
use App\Models\User;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Mail::fake();
});

it('emails a link back to the page the guest was reading, on the configured host', function (): void {
    config(['app.url' => 'https://dev-null.sh']);

    $this->from('/log/trending')
        ->post('http://evil.example'.route('join-link.store', absolute: false), ['email' => 'ada@example.com', 'path' => '/@ada/log?tab=replies'])
        ->assertRedirect('/log/trending')
        ->assertSessionHasNoErrors();

    Mail::assertQueued(JoinLinkEmail::class, function (JoinLinkEmail $mail): bool {
        $html = $mail->render();

        return $mail->hasTo('ada@example.com')
            && $mail->hasSubject('Your link to /dev/null')
            && str_contains($html, e('https://dev-null.sh/@ada/log?tab=replies#join-link'));
    });
});

it('emails each address once a month at most', function (): void {
    $this->post(route('join-link.store'), ['email' => 'ada@example.com', 'path' => '/'])->assertSessionHasNoErrors();
    $this->post(route('join-link.store'), ['email' => 'Ada@Example.com', 'path' => '/'])->assertSessionHasNoErrors();

    Mail::assertQueuedCount(1);

    $this->travel(31)->days();

    $this->post(route('join-link.store'), ['email' => 'ada@example.com', 'path' => '/'])->assertSessionHasNoErrors();

    Mail::assertQueuedCount(2);
});

it('answers bots that fill in the hidden field as if the email went out', function (): void {
    $this->post(route('join-link.store'), ['email' => 'ada@example.com', 'path' => '/', 'homepage' => 'https://spam.example'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Mail::assertNothingQueued();
});

it('only links to pages of this site', function (string $path): void {
    $this->post(route('join-link.store'), ['email' => 'ada@example.com', 'path' => $path])
        ->assertSessionHasErrors('path');

    Mail::assertNothingQueued();
})->with([
    'another site' => 'https://evil.example',
    'a protocol-relative URL' => '//evil.example',
    'a backslash' => '/\evil.example',
    'whitespace' => '/log javascript:alert(1)',
]);

it('rejects what is not an email address', function (): void {
    $this->post(route('join-link.store'), ['email' => 'not an email', 'path' => '/'])
        ->assertSessionHasErrors('email');

    Mail::assertNothingQueued();
});

it('limits how many links an IP address asks for', function (): void {
    foreach (['a', 'b', 'c'] as $name) {
        $this->post(route('join-link.store'), ['email' => "{$name}@example.com", 'path' => '/'])->assertSessionHasNoErrors();
    }

    $this->post(route('join-link.store'), ['email' => 'd@example.com', 'path' => '/'])
        ->assertRedirect()
        ->assertSessionHasErrors(['email' => 'Too many links sent from here. Try again later.']);

    Mail::assertQueuedCount(3);
});

it('is only for guests', function (): void {
    $this->actingAs(User::factory()->create())
        ->post(route('join-link.store'), ['email' => 'ada@example.com', 'path' => '/'])
        ->assertRedirect();

    Mail::assertNothingQueued();
});

it('keeps the address encrypted in the queue', function (): void {
    Queue::push(new SendQueuedMailable(new JoinLinkEmail('https://dev-null.sh/')->to('ada@example.com')));

    expect(DB::table('jobs')->value('payload'))->not->toContain('ada@example.com');
});
