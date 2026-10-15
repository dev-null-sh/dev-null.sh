import type { FocusEvent } from 'react';
import { useState, useSyncExternalStore } from 'react';

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

const storage = (): Storage | null => {
    try {
        return typeof window === 'undefined' ? null : window.localStorage;
    } catch {
        return null;
    }
};

const read = (key: string): string => {
    if (pending.has(key)) {
        return pending.get(key) ?? '';
    }

    try {
        return storage()?.getItem(key) ?? '';
    } catch {
        return '';
    }
};

const write = (key: string, body: string): void => {
    try {
        if (body === '') {
            storage()?.removeItem(key);
        } else {
            storage()?.setItem(key, body);
        }
    } catch {
        return;
    }
};

const storedKeys = (): string[] => {
    try {
        const store = storage();

        return Array.from(
            { length: store?.length ?? 0 },
            (_, index) => store?.key(index) ?? '',
        );
    } catch {
        return [];
    }
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const flush = (): void => {
    clearTimeout(timeout);
    pending.forEach((body, key) => write(key, body));
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
    write(key, '');
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
            write(key, '');
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
