import type { User } from '@/types/auth';

export type EventState = 'upcoming' | 'live' | 'ended' | 'cancelled';

export type EventResponse = 'going' | 'interested';

export type EventAgendaItem = {
    id: number;
    label: string;
    starts_at: string;
};

export type Event = {
    id: string;
    commit_id: string;
    title: string;
    starts_at: string;
    ends_at: string;
    location: string | null;
    url: string | null;
    cancelled_at: string | null;
    state: EventState;
    going_count?: number;
    agenda_items?: EventAgendaItem[];
};

export type EventWithHost = Event & {
    host: User;
    going_count: number;
};
