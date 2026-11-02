<?php

declare(strict_types=1);

use App\Models\Message;
use App\Models\MessageVoice;
use App\Models\User;
use App\Services\EncryptedFiles;
use App\Services\VoiceNotes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake();
    config(['remotes.requests' => true, 'remotes.everyone' => true]);
});

function sendVoiceNote(User $sender, User $recipient): MessageVoice
{
    test()->actingAs($sender)->post(route('conversation-message.store', $recipient), [
        'client_id' => Str::uuid()->toString(),
        'voice' => voiceNoteUpload(),
        'voice_waveform' => voiceNoteWaveform(),
    ], ['Accept' => 'application/json'])->assertCreated();

    return MessageVoice::query()->latest('created_at')->firstOrFail();
}

it('serves voice notes, decrypted, only to the people in the conversation', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $voice = sendVoiceNote($mark, $nuno);

    foreach ([$mark, $nuno] as $participant) {
        $response = $this->actingAs($participant)->get(route('message-voice.show', $voice))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mp4')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'max-age=3600, private');

        expect($response->getContent())->toBe(resolve(VoiceNotes::class)->rebuild(voiceNoteWebm())['file'] ?? null)
            ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'; sandbox");
    }

    $this->actingAs(User::factory()->create())->get(route('message-voice.show', $voice))->assertNotFound();
});

it('keeps voice notes encrypted at rest', function (): void {
    $voice = sendVoiceNote(User::factory()->withRemotes()->create(), User::factory()->withRemotes()->create());

    $stored = (string) Storage::disk()->get($voice->path);

    expect($stored)->not->toContain('dOps')
        ->and(resolve(EncryptedFiles::class)->decrypt($stored))->toContain('ftypisom');
});

it('serves WAV voice notes as WAV', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $voice = MessageVoice::factory()->for(sendChatMessage($mark, $nuno))->create();
    Storage::disk()->put($voice->path, resolve(EncryptedFiles::class)->encrypt(voiceNoteWav()));

    $this->actingAs($nuno)->get(route('message-voice.show', $voice))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/wav');
});

it('keeps voice notes out of the history an org member cannot see', function (): void {
    $mark = User::factory()->create();
    [$nuno, $taylor] = followersOf($mark, 2);
    $org = createOrg($mark, [$nuno]);
    $before = MessageVoice::factory()->for(pushToOrg($nuno, $org))->create();
    Storage::disk()->put($before->path, resolve(EncryptedFiles::class)->encrypt(voiceNoteWav()));
    $this->actingAs($mark)->postJson(route('org-member.store', $org), ['members' => [$taylor->handle], 'share_history' => false])->assertNoContent();
    $after = MessageVoice::factory()->for(pushToOrg($nuno, $org))->create();
    Storage::disk()->put($after->path, resolve(EncryptedFiles::class)->encrypt(voiceNoteWav()));

    $this->actingAs($taylor)->get(route('message-voice.show', $before))->assertNotFound();
    $this->get(route('message-voice.show', $after))->assertOk();
});

it('is not found when the file is gone', function (): void {
    $mark = User::factory()->withRemotes()->create();
    $nuno = User::factory()->withRemotes()->create();
    $voice = MessageVoice::factory()->for(sendChatMessage($mark, $nuno))->create();

    $this->actingAs($nuno)->get(route('message-voice.show', $voice))->assertNotFound();
});

it('requires authentication', function (): void {
    $voice = MessageVoice::factory()->for(Message::factory())->create();

    $this->get(route('message-voice.show', $voice))->assertRedirect();
});
