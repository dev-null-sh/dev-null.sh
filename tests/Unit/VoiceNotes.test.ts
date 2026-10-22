import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    barsOf,
    encodeWav,
    formatVoiceTime,
    voiceBars,
    voiceSampleRate,
    voiceVolumeOf,
    waveformOf,
} from '../../resources/js/lib/voice-notes.ts';

void test('encodes 16 kHz mono 16-bit PCM with nothing but the format and the samples', () => {
    const wav = new DataView(encodeWav(new Float32Array([0, 1, -1, 0.5, 2])));
    const text = (offset: number) =>
        String.fromCharCode(
            ...Array.from({ length: 4 }, (_, index) =>
                wav.getUint8(offset + index),
            ),
        );

    assert.equal(wav.byteLength, 44 + 10);
    assert.equal(text(0), 'RIFF');
    assert.equal(wav.getUint32(4, true), 36 + 10);
    assert.equal(text(8), 'WAVE');
    assert.equal(text(12), 'fmt ');
    assert.equal(wav.getUint32(16, true), 16);
    assert.equal(wav.getUint16(20, true), 1);
    assert.equal(wav.getUint16(22, true), 1);
    assert.equal(wav.getUint32(24, true), voiceSampleRate);
    assert.equal(wav.getUint32(28, true), voiceSampleRate * 2);
    assert.equal(wav.getUint16(32, true), 2);
    assert.equal(wav.getUint16(34, true), 16);
    assert.equal(text(36), 'data');
    assert.equal(wav.getUint32(40, true), 10);
    assert.deepEqual(
        [0, 1, 2, 3, 4].map((index) => wav.getInt16(44 + index * 2, true)),
        [0, 32767, -32768, 16383, 32767],
    );
});

void test('draws the loudest part of each slice the way the server does', () => {
    const samples = new Float32Array(voiceSampleRate).fill(0.25);
    samples.fill(-1, 0, 100);

    const waveform = waveformOf(samples);

    assert.equal(waveform.length, voiceBars);
    assert.equal(waveform[0], 100);
    assert.ok(waveform.slice(1).every((bar) => bar === 50));
    assert.deepEqual(
        waveformOf(new Float32Array(480)),
        Array(voiceBars).fill(0),
    );
});

void test('prints minutes and seconds', () => {
    assert.equal(formatVoiceTime(0), '0:00');
    assert.equal(formatVoiceTime(7_999), '0:07');
    assert.equal(formatVoiceTime(60_000), '1:00');
    assert.equal(formatVoiceTime(-5), '0:00');
});

void test('draws the bars of an Opus note from the levels heard while recording', () => {
    const levels = Array.from({ length: 96 }, (_, index) =>
        index < 2 ? 1 : 0.5,
    );

    const bars = barsOf(levels);

    assert.equal(bars.length, voiceBars);
    assert.equal(bars[0], 100);
    assert.ok(bars.slice(1).every((bar) => bar === 50));
    assert.deepEqual(barsOf([0.25, 2]).slice(0, 1), [25]);
    assert.equal(barsOf([0.25, 2])[voiceBars - 1], 100);
    assert.deepEqual(barsOf([]), Array(voiceBars).fill(0));
});

void test('reads the saved volume as a whole percent, at full volume until a sensible one is saved', () => {
    assert.equal(voiceVolumeOf(''), 1);
    assert.equal(voiceVolumeOf('loud'), 1);
    assert.equal(voiceVolumeOf('Infinity'), 1);
    assert.equal(voiceVolumeOf('0'), 0);
    assert.equal(voiceVolumeOf('0.4237'), 0.42);
    assert.equal(voiceVolumeOf('-0.3'), 0);
    assert.equal(voiceVolumeOf('1.5'), 1);
});
