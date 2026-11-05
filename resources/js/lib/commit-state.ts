import { http } from '@inertiajs/react';
import type { SharedValue } from '@/hooks/use-shared-state';
import { refreshSharedState } from '@/hooks/use-shared-state';

const fields = [
    'starred',
    'stars_count',
    'pasteis_count',
    'pasteis_received',
    'replies_count',
    'commits_count',
    'continuation_id',
] as const;

type CommitStateField = (typeof fields)[number];

export const commitStateKey = (field: CommitStateField, id: string): string =>
    `${field}:${id}`;

const localChanges = new Map<string, number>();

/**
 * Marks a count this page just changed itself, as a reply or its deletion does, so a read started before that can't
 * put the old count back.
 */
export function changeCountLocally(field: CommitStateField, id: string): void {
    const key = commitStateKey(field, id);
    localChanges.set(key, (localChanges.get(key) ?? 0) + 1);
}

export function localCountVersion(field: CommitStateField, id: string): number {
    return localChanges.get(commitStateKey(field, id)) ?? 0;
}

const parse = (data: string): unknown => {
    try {
        return JSON.parse(data);
    } catch {
        return null;
    }
};

function collect(value: unknown, fresh: Map<string, SharedValue>): void {
    if (Array.isArray(value)) {
        value.forEach((item) => collect(item, fresh));

        return;
    }

    if (typeof value !== 'object' || value === null) {
        return;
    }

    const record = value as Record<string, unknown>;

    if (typeof record.id === 'string') {
        for (const field of fields) {
            const fieldValue = record[field];

            if (
                typeof fieldValue === 'boolean' ||
                typeof fieldValue === 'number' ||
                typeof fieldValue === 'string' ||
                (field === 'continuation_id' && fieldValue === null)
            ) {
                fresh.set(commitStateKey(field, record.id), fieldValue);
            }
        }
    }

    Object.values(record).forEach((item) => collect(item, fresh));
}

export function initializeCommitState(): void {
    http.onResponse((response) => {
        const fresh = new Map<string, SharedValue>();

        collect(parse(response.data), fresh);
        refreshSharedState(fresh);

        return response;
    });
}
