export type EventFormat = 'onsite' | 'online' | 'hybrid';

export type EventState = 'upcoming' | 'live' | 'ended' | 'cancelled';

export type EventCalendarLinks = {
    ics_url: string;
    apple_url: string;
    google_url: string;
    outlook_url: string;
};

export type EventCover = {
    url: string;
    width: number;
    height: number;
};

export type CommitEvent = {
    id: string;
    title: string;
    starts_at: string;
    ends_at: string;
    timezone: string;
    format: EventFormat;
    venue: string | null;
    address: string | null;
    platform: string | null;
    platform_label: string | null;
    join_url: string | null;
    cover: EventCover | null;
    attendees_count: number;
    is_going: boolean;
    state: EventState;
    updated: boolean;
    calendar: EventCalendarLinks | null;
};
