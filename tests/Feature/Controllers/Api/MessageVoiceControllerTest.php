<?php

declare(strict_types=1);

use App\Models\Message;
use App\Models\User;
use App\Services\EncryptedFiles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake();
    config(['remotes.requests' => true, 'remotes.everyone' => true]);

    $this->user = User::factory()->withRemotes()->create();
    $this->keyPair = apiKeyPairFor($this->user);
    $this->nuno = User::factory()->withRemotes()->create();

    $this->actingAs($this->nuno)->post(route('conversation-message.store', $this->user), [
        'client_id' => Str::uuid()->toString(),
        'voice' => voiceNoteUpload(),
        'voice_waveform' => voiceNoteWaveform(),
    ], ['Accept' => 'application/json'])->assertCreated();

    $this->voice = Message::query()->sole()->voice;
});

function signedVoiceUrl(): string
{
    return signedApiRequest(test()->keyPair, 'GET', route('api-conversation-message.index', test()->nuno))->json('data.messages.0.voice.url');
}

it('serves a voice note from the URL a thread hands out, without signing the request', function (): void {
    $this->travelTo(now()->setTime(10, 0));

    $url = signedVoiceUrl();

    expect($url)->toStartWith(route('api-message-voice.show', $this->voice).'?expires='.today()->addDays(2)->timestamp);

    $this->travelTo(now()->setTime(23, 0));

    expect(signedVoiceUrl())->toBe($url);

    $response = $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mp4')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'max-age=3600, private');

    expect($response->getContent())->toBe(resolve(EncryptedFiles::class)->decrypt((string) Storage::disk()->get((string) $this->voice?->path)));
});

it('says a reply answers a voice note', function (): void {
    signedApiRequest($this->keyPair, 'POST', route('api-conversation-message.store', $this->nuno), [
        'body' => 'nice',
        'client_id' => Str::uuid()->toString(),
        'reply_to_id' => $this->voice?->message_id,
    ])->assertCreated()->assertJsonPath('data.reply_to.has_voice', true);
});

it('stops serving it once the URL expires or is changed', function (): void {
    $url = signedVoiceUrl();

    $this->get(str_replace('user='.$this->user->id, 'user='.$this->nuno->id, $url))->assertForbidden();

    $this->travelTo(today()->addDays(2)->addSecond());

    $this->get($url)->assertForbidden();
});

it('does not serve a voice note whose file is gone', function (): void {
    $url = signedVoiceUrl();

    Storage::disk()->delete((string) $this->voice?->path);

    $this->get($url)->assertNotFound();
});

it('signs the URL for one person only', function (): void {
    $stranger = User::factory()->create();

    $this->get(url(URL::temporarySignedRoute('api-message-voice.show', now()->addHour(), ['messageVoice' => $this->voice, 'user' => $stranger->id], absolute: false)))
        ->assertNotFound();
});
