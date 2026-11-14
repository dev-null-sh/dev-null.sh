import { useEffect, useSyncExternalStore } from 'react';

export type ApiDocsSchema = { code: string; html: string };

export type ApiDocsSchemas = {
    body: ApiDocsSchema | null;
    responses: Record<string, ApiDocsSchema>;
};

export type SchemasState = ApiDocsSchemas | 'loading' | 'failed' | undefined;

const loaded = new Map<string, ApiDocsSchemas | 'loading' | 'failed'>();
const listeners = new Set<() => void>();

function notify(): void {
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Asks for an endpoint's schemas once, and keeps them for every place on the page that shows them. Schemas that failed
 * to load are asked for again, the failure showing until then.
 */
export async function loadSchemas(url: string): Promise<void> {
    const state = loaded.get(url);

    if (state !== undefined && state !== 'failed') {
        return;
    }

    loaded.set(url, 'loading');
    notify();

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`${response.status}`);
        }

        loaded.set(url, (await response.json()) as ApiDocsSchemas);
    } catch {
        loaded.set(url, 'failed');
    }

    notify();
}

/**
 * An endpoint's schemas, asked for only while `url` is given, so a reader reading fields never loads them.
 */
export function useSchemas(url: string | null): SchemasState {
    const state = useSyncExternalStore(
        subscribe,
        () => (url === null ? undefined : loaded.get(url)),
        () => undefined,
    );

    useEffect(() => {
        if (url !== null) {
            void loadSchemas(url);
        }
    }, [url]);

    return state;
}
