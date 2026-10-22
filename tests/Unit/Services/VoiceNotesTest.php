<?php

declare(strict_types=1);

use App\Services\VoiceNotes;

it('rebuilds an Opus WebM recording as MP4 from its packets alone', function (bool $blockGroups): void {
    $rebuilt = resolve(VoiceNotes::class)->rebuild(voiceNoteWebm(2000, blockGroups: $blockGroups));

    expect($rebuilt)->not->toBeNull()
        ->and($rebuilt['duration_ms'] ?? null)->toBe(2000)
        ->and(mb_substr($rebuilt['file'] ?? '', 4, 8, '8bit'))->toBe('ftypisom')
        ->and($rebuilt['file'] ?? '')->not->toContain('secret title')
        ->not->toContain('Chrome')
        ->toContain('dOps')
        ->toContain('stts'.pack('N2', 0, 1).pack('N2', 100, 960))
        ->toContain(pack('N', 8 + 100 * 41).'mdat')
        ->and(resolve(VoiceNotes::class)->type($rebuilt['file'] ?? ''))->toBe('audio/mp4');
})->with(['simple blocks' => false, 'block groups' => true]);

it('rebuilds a recording made by a real Opus encoder, dropping its tags', function (): void {
    $rebuilt = resolve(VoiceNotes::class)->rebuild((string) file_get_contents(__DIR__.'/../../Fixtures/voice-note.webm'));

    expect($rebuilt['duration_ms'] ?? null)->toBe(1513)
        ->and($rebuilt['file'] ?? '')->not->toContain('secret title')
        ->not->toContain('Lavf');
});

it('times packets from their table of contents', function (string $toc, int $milliseconds): void {
    expect(resolve(VoiceNotes::class)->rebuild(voiceNoteWebm(2000, toc: $toc))['duration_ms'] ?? null)->toBe($milliseconds);
})->with([
    '10 ms SILK' => ["\x00", 1000],
    '60 ms SILK' => ["\x18", 6000],
    '20 ms hybrid' => ["\x68", 2000],
    'two 20 ms CELT frames' => ["\xF9", 4000],
    'three 20 ms CELT frames' => ["\xFB\x03", 6000],
]);

it('keeps runs of packet lengths in the sample table', function (): void {
    $file = resolve(VoiceNotes::class)->rebuild(voiceNoteWebm(2000, toc: "\xF9"))['file'] ?? '';

    expect($file)->toContain('stts'.pack('N2', 0, 1).pack('N2', 100, 1920));
});

it('rebuilds a 16 kHz mono WAV recording from its samples alone', function (): void {
    $metadata = 'LIST'.pack('V', 21).'INFOISFT'.pack('V', 9).'recorder!'."\0";

    $rebuilt = resolve(VoiceNotes::class)->rebuild(voiceNoteWav(1000, chunks: $metadata));

    expect($rebuilt)->toBe(['file' => voiceNoteWav(1000), 'duration_ms' => 1000])
        ->and(resolve(VoiceNotes::class)->type(voiceNoteWav()))->toBe('audio/wav');
});

it('refuses anything but a voice note', function (string $upload): void {
    expect(resolve(VoiceNotes::class)->rebuild($upload))->toBeNull();
})->with([
    'nothing' => fn (): string => '',
    'html' => fn (): string => '<script>alert(1)</script>',
    'ogg' => fn (): string => 'OggS'.str_repeat("\0", 64_000),
    'not wave' => fn (): string => 'RIFF'.pack('V', 4).'AVI ',
    'wav at another sample rate' => fn (): string => voiceNoteWav(sampleRate: 48_000),
    'stereo wav' => fn (): string => voiceNoteWav(channels: 2),
    'wav samples before the format' => fn (): string => 'RIFF'.pack('V', 4 + 8 + 32_000).'WAVEdata'.pack('V', 32_000).str_repeat("\0", 32_000),
    'wav chunk running past the end' => fn (): string => mb_substr(voiceNoteWav(), 0, 10_000, '8bit'),
    'wav without samples' => fn (): string => mb_substr(voiceNoteWav(), 0, 36, '8bit'),
    'wav with an odd number of bytes' => fn (): string => 'RIFF'.pack('V', 4 + 24 + 8 + 16_001).'WAVEfmt '.pack('V', 16).pack('vvVVvv', 1, 1, 16_000, 32_000, 2, 16).'data'.pack('V', 16_001).str_repeat("\0", 16_001)."\0",
    'wav shorter than half a second' => fn (): string => voiceNoteWav(400),
    'wav longer than a minute' => fn (): string => voiceNoteWav(62_000),
    'webm shorter than half a second' => fn (): string => voiceNoteWebm(400),
    'webm longer than a minute' => fn (): string => voiceNoteWebm(62_000),
    'webm of another codec' => fn (): string => voiceNoteWebm(codec: 'A_VORBIS'),
    'webm with three channels' => fn (): string => voiceNoteWebm(channels: 3),
    'webm with two tracks' => fn (): string => voiceNoteWebm(tracks: 2),
    'webm with laced frames' => fn (): string => voiceNoteWebm(flags: 0x82),
    'webm with an empty frame count' => fn (): string => voiceNoteWebm(toc: "\xFB\x00"),
    'webm with a frame count and no count' => fn (): string => voiceNoteWebm(toc: "\xFB"),
    'webm over 120 ms a packet' => fn (): string => voiceNoteWebm(toc: "\xFB\x07"),
    'webm cut short' => fn (): string => mb_substr(voiceNoteWebm(), 0, 200, '8bit'),
    'matroska' => fn (): string => str_replace('webm', 'mkv!', voiceNoteWebm()),
    'webm without a segment' => fn (): string => ebmlElement(0x1A45DFA3, ebmlElement(0x4282, 'webm')),
    'webm with an element of unknown size' => fn (): string => ebmlElement(0x1A45DFA3, ebmlElement(0x4282, 'webm')).ebmlElement(0x18538067, ebmlElement(0x1654AE6B, 'x', unknownSize: true)),
    'webm with a broken element in a cluster' => fn (): string => ebmlElement(0x1A45DFA3, ebmlElement(0x4282, 'webm')).ebmlElement(0x18538067, ebmlElement(0x1F43B675, "\x00", unknownSize: true)),
    'webm with an element of unknown size in a cluster' => fn (): string => ebmlElement(0x1A45DFA3, ebmlElement(0x4282, 'webm')).ebmlElement(0x18538067, ebmlElement(0x1F43B675, ebmlElement(0xA0, '', unknownSize: true), unknownSize: true)),
    'webm with a block group without a block' => fn (): string => str_replace(ebmlElement(0xA3, "\x81\x00\x00\x80\xF8".str_repeat("\x5A", 40)), ebmlElement(0xA0, ebmlElement(0xFB, "\x00")), voiceNoteWebm()),
    'webm with a block of another track' => fn (): string => str_replace("\xA3\x01\x00\x00\x00\x00\x00\x00\x2D\x81", "\xA3\x01\x00\x00\x00\x00\x00\x00\x2D\x82", voiceNoteWebm()),
    'webm with an empty block' => fn (): string => ebmlElement(0x1A45DFA3, ebmlElement(0x4282, 'webm')).ebmlElement(0x18538067, ebmlElement(0x1654AE6B, ebmlElement(0xAE, ebmlElement(0xD7, "\x01").ebmlElement(0x83, "\x02").ebmlElement(0x86, 'A_OPUS').ebmlElement(0x63A2, 'OpusHead'.pack('CCvVvC', 1, 1, 0, 48000, 0, 0)))).ebmlElement(0x1F43B675, ebmlElement(0xA3, "\x81\x00\x00\x80"))),
    'webm with an id too long' => fn (): string => "\x08\x00\x00\x00\x00",
    'webm ending after an id' => fn (): string => ebmlElement(0x1A45DFA3, ebmlElement(0x4282, 'webm'))."\x18\x53\x80\x67",
]);
