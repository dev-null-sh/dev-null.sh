import type { FocusEvent } from 'react';
import { useState, useSyncExternalStore } from 'react';
import { readStorage, storedKeys, writeStorage } from '@/lib/storage';

export type UseStashReturn = {
    readonly body: string;
    readonly isRestored: boolean;
    readonly updateBody: (body: string) => void;
    readonly drop: () => void;
};

export type UseSeriesStashReturn = {
    readonly bodies: string[];
    readonly isRestored: boolean;
    readonly updateBody: (index: number, body: string) => void;
    readonly insertPart: (index: number) => void;
    readonly removePart: (index: number) => void;
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

/**
 * Guests never stash anything, as they sign in before they write.
 */
export function useHasStash(userId: string | null, name: string): boolean {
    const key = userId === null ? null : keyFor(userId, name);

    return useSyncExternalStore(
        subscribe,
        () => key !== null && read(key) !== '',
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

const readLaterBodies = (key: string): string[] => {
    try {
        const stored: unknown = JSON.parse(read(key) || '[]');

        return Array.isArray(stored)
            ? stored.filter((body): body is string => typeof body === 'string')
            : [];
    } catch {
        return [];
    }
};

export function useSeriesStash(
    userId: string,
    name: string,
): UseSeriesStashReturn {
    const key = keyFor(userId, name);
    const laterKey = `${key}:later`;
    const [bodies, setBodies] = useState(() => [
        read(key),
        ...readLaterBodies(laterKey),
    ]);
    const [isRestored, setIsRestored] = useState(() =>
        bodies.some((body) => body !== ''),
    );

    const store = (next: string[]): void => {
        const [first = '', ...later] = next;

        setBodies(next);

        if (first === '') {
            discard(key);
        } else {
            schedule(key, first);
        }

        if (later.some((body) => body !== '')) {
            schedule(laterKey, JSON.stringify(later));
        } else {
            discard(laterKey);
        }

        if (next.every((body) => body === '')) {
            setIsRestored(false);
        }
    };

    return {
        bodies,
        isRestored,
        updateBody: (index, body) =>
            store(bodies.map((current, at) => (at === index ? body : current))),
        insertPart: (index) =>
            store([...bodies.slice(0, index), '', ...bodies.slice(index)]),
        removePart: (index) => store(bodies.filter((_, at) => at !== index)),
        drop: () => {
            setBodies(['']);
            setIsRestored(false);
            discard(key);
            discard(laterKey);
        },
    } as const;
}
