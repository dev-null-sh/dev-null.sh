import { useSyncExternalStore } from 'react';
import { readStorage, writeStorage } from '@/lib/storage';
import { voiceVolumeOf } from '@/lib/voice-notes';

const key = 'voice-note-volume';
const listeners = new Set<() => void>();
let isAdjustable: boolean | null = null;

function subscribe(listener: () => void) {
    listeners.add(listener);
    window.addEventListener('storage', listener);

    return () => {
        listeners.delete(listener);
        window.removeEventListener('storage', listener);
    };
}

/**
 * iOS plays every page's audio at the device's volume and ignores the one a page sets.
 */
function adjustableVolume(): number | null {
    if (isAdjustable === null) {
        const probe = new Audio();
        probe.volume = 0.5;
        isAdjustable = probe.volume === 0.5;
    }

    return isAdjustable ? voiceVolume() : null;
}

export function voiceVolume(): number {
    return voiceVolumeOf(readStorage(key));
}

export function setVoiceVolume(volume: number): void {
    writeStorage(key, String(voiceVolumeOf(String(volume))));
    listeners.forEach((listener) => listener());
}

/**
 * The volume every voice note plays at on this device, or null where the browser does not let a page change it.
 */
export function useVoiceVolume(): number | null {
    return useSyncExternalStore(subscribe, adjustableVolume, () => 1);
}
