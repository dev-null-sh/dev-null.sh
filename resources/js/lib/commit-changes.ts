import { index } from '@/actions/App/Http/Controllers/CommitChangeController';

export type CommitChange = {
    changed: boolean;
    replies: string[];
    truncated: boolean;
    translated: boolean;
};

/**
 * The server's clock minus the browser's, so a page dates what it read on the server's clock.
 */
let serverOffset = 0;

/**
 * What the page last read is dated a little earlier than it was, for whatever was changing while it read.
 */
const margin = 5_000;

export function setServerTime(serverNow: number): void {
    serverOffset = serverNow - Date.now();
}

/**
 * Now, or the given time on the browser's clock, on the server's clock minus the margin: when something read then was
 * read, to ask what changed since.
 */
export function readAt(at = Date.now()): number {
    return at + serverOffset - margin;
}

type Waiting = {
    id: string;
    since: number;
    resolve: (change: CommitChange | null) => void;
};

let waiting: Waiting[] = [];
let timer: ReturnType<typeof setTimeout> | undefined;

async function ask(batch: Waiting[]): Promise<void> {
    const since = Math.min(...batch.map((entry) => entry.since));
    const ids = [...new Set(batch.map((entry) => entry.id))];

    try {
        const response = await fetch(
            index.url({ query: { ids, since: Math.floor(since) } }),
            { headers: { Accept: 'application/json' }, cache: 'no-store' },
        );
        const changes = response.ok
            ? (
                  (await response.json()) as {
                      changes: Record<string, CommitChange>;
                  }
              ).changes
            : null;

        batch.forEach((entry) => entry.resolve(changes?.[entry.id] ?? null));
    } catch {
        batch.forEach((entry) => entry.resolve(null));
    }
}

function flush(): void {
    timer = undefined;
    const pending = waiting;
    waiting = [];

    for (let offset = 0; offset < pending.length; offset += 100) {
        void ask(pending.slice(offset, offset + 100));
    }
}

/**
 * What changed about a commit since the given time, asked together with every other commit asking at about the same
 * time, so a page catching up after missing live updates makes one request rather than one per commit. Resolves to
 * null when it couldn't tell, which callers treat as a change.
 */
export function changesSince(
    id: string,
    since: number,
): Promise<CommitChange | null> {
    return new Promise((resolve) => {
        waiting.push({ id, since, resolve });
        timer ??= setTimeout(flush, 50);
    });
}
