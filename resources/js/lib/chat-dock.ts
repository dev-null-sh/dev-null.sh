import { useSyncExternalStore } from 'react';
import { readStorage, writeStorage } from '@/lib/storage';

/**
 * The open conversation is kept by its key: the partner's handle, or `org:{id}`.
 */
export type ChatDock = {
    isDocked: boolean;
    isExpanded: boolean;
    conversation: string | null;
};

const storageKey = 'chat-dock';
const undocked: ChatDock = {
    isDocked: false,
    isExpanded: false,
    conversation: null,
};
const listeners = new Set<() => void>();
let dock: ChatDock | null = null;

function read(): ChatDock {
    if (dock !== null) {
        return dock;
    }

    try {
        const stored = JSON.parse(
            readStorage(storageKey) || 'null',
        ) as Partial<ChatDock> | null;

        dock = {
            isDocked: stored?.isDocked === true,
            isExpanded: stored?.isExpanded === true,
            conversation:
                typeof stored?.conversation === 'string'
                    ? stored.conversation
                    : null,
        };
    } catch {
        dock = undocked;
    }

    return dock;
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    const onStorage = (event: StorageEvent) => {
        if (event.key === storageKey) {
            dock = null;
            listener();
        }
    };

    window.addEventListener('storage', onStorage);

    return () => {
        listeners.delete(listener);
        window.removeEventListener('storage', onStorage);
    };
}

export function updateChatDock(change: Partial<ChatDock>): void {
    dock = { ...read(), ...change };
    writeStorage(storageKey, dock.isDocked ? JSON.stringify(dock) : '');
    listeners.forEach((listener) => listener());
}

/**
 * Docking starts minimized, so turning it on never covers the page the user is about to read.
 */
export function setChatDocked(isDocked: boolean): void {
    updateChatDock(
        isDocked ? { isDocked, isExpanded: false } : { ...undocked },
    );
}

export function useChatDock(): ChatDock {
    return useSyncExternalStore(subscribe, read, () => undocked);
}
