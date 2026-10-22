import { useSyncExternalStore } from 'react';
import { readStorage, writeStorage } from '@/lib/storage';
import { voiceVolumeOf } from '@/lib/voice-notes';

const key = 'voice-note-volume';
const listeners = new Set<() => void>();
let volume: number | null = null;

// Another tab saved a volume, or cleared everything it saved. Heard for as long as the page lives, so the volume
// kept in memory is never stale, not even after leaving every voice note behind.
if (typeof window !== 'undefined') {
    window.addEventListener('storage', (event: StorageEvent) => {
        if (event.key === key || event.key === null) {
            volume = null;
            listeners.forEach((listener) => listener());
        }
    });
}

function subscribe(listener: () => void) {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Kept in memory as well, so the volume still changes when the browser can't save it.
 */
export function voiceVolume(): number {
    volume ??= voiceVolumeOf(readStorage(key));

    return volume;
}

export function setVoiceVolume(to: number): void {
    volume = voiceVolumeOf(String(to));
    writeStorage(key, String(volume));
    listeners.forEach((listener) => listener());
}

/**
 * The volume every voice note plays at on this device.
 */
export function useVoiceVolume(): number {
    return useSyncExternalStore(subscribe, voiceVolume, () => 1);
}
