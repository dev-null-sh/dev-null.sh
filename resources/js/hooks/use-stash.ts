import type { FocusEvent } from 'react';
import { useState, useSyncExternalStore } from 'react';
import { readStorage, storedKeys, writeStorage } from '@/lib/storage';

export type UseStashReturn = {
    readonly body: string;
    readonly isRestored: boolean;
    readonly updateBody: (body: string) => void;
    readonly drop: () => void;
};

const listeners = new Set<() => void>();
const pending = new Map<string, string>();
let timeout: ReturnType<typeof setTimeout> | undefined;
let isListeningForPageHide = false;

const prefixFor = (userId: string): string => `stash:${userId}:`;

const keyFor = (userId: string, name: string): string =>
    `${prefixFor(userId)}${name}`;

const read = (key: string): string =>
    pending.has(key) ? (pending.get(key) ?? '') : readStorage(key);

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const flush = (): void => {
    clearTimeout(timeout);
    pending.forEach((body, key) => writeStorage(key, body));
    pending.clear();
    notify();
};

const schedule = (key: string, body: string): void => {
    if (!isListeningForPageHide) {
        window.addEventListener('pagehide', flush);
        isListeningForPageHide = true;
    }

    pending.set(key, body);
    clearTimeout(timeout);
    timeout = setTimeout(flush, 300);
};

const discard = (key: string): void => {
    pending.delete(key);
    writeStorage(key, '');
    notify();
};

export function moveCaretToEnd(event: FocusEvent<HTMLTextAreaElement>): void {
    const textarea = event.currentTarget;

    if (textarea.selectionStart === 0 && textarea.selectionEnd === 0) {
        textarea.setSelectionRange(
            textarea.value.length,
            textarea.value.length,
        );
    }
}

export function clearStashes(userId: string): void {
    const prefix = prefixFor(userId);

    [...pending.keys(), ...storedKeys()]
        .filter((key) => key.startsWith(prefix))
        .forEach((key) => {
            pending.delete(key);
            writeStorage(key, '');
        });

    notify();
}

export function useHasStash(userId: string, name: string): boolean {
    const key = keyFor(userId, name);

    return useSyncExternalStore(
        subscribe,
        () => read(key) !== '',
        () => false,
    );
}

export function useStash(userId: string, name: string): UseStashReturn {
    const key = keyFor(userId, name);
    const [body, setBody] = useState(() => read(key));
    const [isRestored, setIsRestored] = useState(() => body !== '');

    const updateBody = (next: string): void => {
        setBody(next);

        if (next === '') {
            setIsRestored(false);
            discard(key);

            return;
        }

        schedule(key, next);
    };

    const drop = (): void => {
        setBody('');
        setIsRestored(false);
        discard(key);
    };

    return { body, isRestored, updateBody, drop } as const;
}
