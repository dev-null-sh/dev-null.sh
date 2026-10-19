import { useSyncExternalStore } from 'react';

const HEX_DIGITS = '0123456789abcdef';
const FRAME_MS = 70;
const FRAMES_PER_CHARACTER = 3;
const HOLD_FRAMES = 10;

export type ScrambledCharacter = {
    character: string;
    isLocked: boolean;
};

const listeners = new Set<() => void>();
let currentFrame: number | null = null;
let interval: ReturnType<typeof setInterval> | undefined;

const subscribe = (listener: () => void) => {
    listeners.add(listener);

    if (
        interval === undefined &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        currentFrame = 0;
        interval = setInterval(() => {
            currentFrame = (currentFrame ?? 0) + 1;
            listeners.forEach((notify) => notify());
        }, FRAME_MS);
    }

    return () => {
        listeners.delete(listener);

        if (listeners.size === 0) {
            clearInterval(interval);
            interval = undefined;
            currentFrame = null;
        }
    };
};

export function scrambleHash(
    hash: string,
    frame: number | null,
): ScrambledCharacter[] {
    const characters = hash.split('');

    if (frame === null) {
        return characters.map((character) => ({ character, isLocked: true }));
    }

    const cycleFrame =
        frame % (characters.length * FRAMES_PER_CHARACTER + HOLD_FRAMES);
    const lockedCount = Math.floor(cycleFrame / FRAMES_PER_CHARACTER);

    return characters.map((character, index) =>
        index < lockedCount
            ? { character, isLocked: true }
            : {
                  character:
                      HEX_DIGITS[
                          (frame * 7 + index * 11 + character.charCodeAt(0)) %
                              HEX_DIGITS.length
                      ],
                  isLocked: false,
              },
    );
}

export function useScrambledHash(hash: string): ScrambledCharacter[] {
    const frame = useSyncExternalStore(
        subscribe,
        () => currentFrame,
        () => null,
    );

    return scrambleHash(hash, frame);
}
