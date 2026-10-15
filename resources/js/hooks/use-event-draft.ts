import type { FormDataConvertible } from '@inertiajs/core';
import { useState } from 'react';
import type { EventFormat } from '@/types';

export type EventDraft = {
    title: string;
    startsDate: string;
    startsTime: string;
    endsDate: string;
    endsTime: string;
    timezone: string;
    format: EventFormat;
    venue: string;
    address: string;
    platform: string;
    joinUrl: string;
    cover: File | null;
};

function localDate(offsetDays: number): string {
    const date = new Date();
    date.setDate(date.getDate() + offsetDays);

    return date.toLocaleDateString('en-CA');
}

export function emptyEventDraft(): EventDraft {
    return {
        title: '',
        startsDate: localDate(7),
        startsTime: '19:00',
        endsDate: localDate(7),
        endsTime: '21:00',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        format: 'onsite',
        venue: '',
        address: '',
        platform: 'youtube',
        joinUrl: '',
        cover: null,
    };
}

/**
 * Fields that do not belong to the chosen format are left out, matching what the server keeps.
 */
export function eventPayload(
    draft: EventDraft,
    { withTitle = true }: { withTitle?: boolean } = {},
): Record<string, FormDataConvertible> {
    const hasVenue = draft.format !== 'online';
    const hasStream = draft.format !== 'onsite';

    return {
        ...(withTitle ? { title: draft.title } : {}),
        starts_at: `${draft.startsDate}T${draft.startsTime}`,
        ends_at: `${draft.endsDate}T${draft.endsTime}`,
        timezone: draft.timezone,
        format: draft.format,
        ...(hasVenue ? { venue: draft.venue, address: draft.address } : {}),
        ...(hasStream
            ? { platform: draft.platform, join_url: draft.joinUrl }
            : {}),
        ...(draft.cover ? { cover: draft.cover } : {}),
    };
}

export function useEventDraft() {
    const [draft, setDraft] = useState<EventDraft | null>(null);

    return {
        draft,
        setDraft,
        start: () => setDraft(emptyEventDraft()),
        clear: () => setDraft(null),
        payload: () => (draft ? eventPayload(draft) : null),
    };
}
