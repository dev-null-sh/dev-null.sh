<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('renders the email as it arrives, with a link to unsubscribe', function (): void {
    $html = $this->actingAs(User::factory()->create(['username' => 'admin']))
        ->postJson(route('email-preview.store'), ['subject' => 'What shipped', 'body' => "## New\n\n- Emails, **finally**"])
        ->assertOk()
        ->json('html');

    expect($html)->toContain('>New</h2>', '>finally</strong>', '/emails/unsubscribe/', '>Unsubscribe</a>');
});

it('does not render previews for non admins', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('email-preview.store'), ['body' => 'Hi'])
        ->assertNotFound();
});
