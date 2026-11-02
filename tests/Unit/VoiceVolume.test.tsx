/// <reference types="node" />
import assert from 'node:assert/strict';
import { test } from 'vite-plus/test';

const saved = new Map<string, string>();
let isStorageFull = false;
const storageListeners: ((event: { key: string | null }) => void)[] = [];

Object.assign(globalThis, {
    window: {
        localStorage: {
            getItem: (key: string) => saved.get(key) ?? null,
            setItem: (key: string, value: string) => {
                if (isStorageFull) {
                    throw new DOMException('Full', 'QuotaExceededError');
                }

                saved.set(key, value);
            },
            removeItem: (key: string) => saved.delete(key),
        },
        addEventListener: (
            type: string,
            listener: (event: { key: string | null }) => void,
        ) => {
            if (type === 'storage') {
                storageListeners.push(listener);
            }
        },
        removeEventListener: () => undefined,
    },
});

const { setVoiceVolume, voiceVolume } =
    await import('../../resources/js/hooks/use-voice-volume');

test('saves the volume for every voice note on this device', () => {
    assert.equal(voiceVolume(), 1);

    setVoiceVolume(0.3);

    assert.equal(voiceVolume(), 0.3);
    assert.equal(saved.get('voice-note-volume'), '0.3');
});

test('keeps the volume set while the browser cannot save it', () => {
    isStorageFull = true;

    setVoiceVolume(0.48);

    assert.equal(voiceVolume(), 0.48);
});

test('follows a volume another tab saves, even with no voice note on screen', () => {
    saved.set('voice-note-volume', '0.7');
    storageListeners.forEach((listener) =>
        listener({ key: 'voice-note-volume' }),
    );

    assert.equal(voiceVolume(), 0.7);
});
