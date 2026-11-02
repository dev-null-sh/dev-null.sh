<?php

declare(strict_types=1);

use App\Models\LinkPreview;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
});

it('gives the link card again, once it was fetched', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($nuno, $this->user, 'read https://laravel.com/docs');

    signedApiRequest($this->keyPair, 'GET', route('api-message-link-preview.show', $message))
        ->assertOk()
        ->assertJsonPath('link_preview.url', 'https://laravel.com/docs')
        ->assertJsonPath('link_preview.is_pending', true);

    LinkPreview::query()->sole()->forceFill(['title' => 'Laravel docs', 'fetched_at' => now()])->save();

    signedApiRequest($this->keyPair, 'GET', route('api-message-link-preview.show', $message))
        ->assertOk()
        ->assertJsonPath('link_preview.title', 'Laravel docs')
        ->assertJsonPath('link_preview.is_pending', false);
});

it('keeps it from people outside the conversation', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $taylor = User::factory()->withRemotes()->create();
    $message = sendChatMessage($nuno, $taylor, 'read https://laravel.com/docs');

    signedApiRequest($this->keyPair, 'GET', route('api-message-link-preview.show', $message))->assertNotFound();
});

it('rejects requests that are not signed', function (): void {
    $nuno = User::factory()->withRemotes()->create();
    $message = sendChatMessage($nuno, $this->user, 'read https://laravel.com/docs');

    $this->getJson(route('api-message-link-preview.show', $message))->assertUnauthorized();
});
