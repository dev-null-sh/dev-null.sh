export type QueueBacklog = {
    queue: string;
    size: number;
    oldest_job_age: number | null;
};

export type FailedJob = {
    uuid: string;
    name: string;
    queue: string;
    exception: string;
    failed_at: string;
};

export type FailedJobs = {
    total: number;
    recent: FailedJob[];
};

export type Connectivity = {
    database: boolean;
    cache: boolean;
};

export type Feature = {
    name: 'translations';
    enabled: boolean;
};

export type LinkPreviewHealth = {
    pending: number;
    failed: number;
};

export type Volume = Record<
    'signups' | 'commits' | 'replies',
    { day: number; week: number }
>;

export type GitHubRateLimit = {
    remaining: number;
    limit: number;
    resets_at: string;
};

export type TrafficRow = {
    name: string;
    visitors: number;
    pageviews: number;
};

export type Traffic = {
    current: number;
    totals: {
        visitors: number;
        pageviews: number;
        avg_duration: number;
        bounce_rate: number;
    };
    breakdowns: Record<
        'pages' | 'referrers' | 'countries' | 'devices' | 'browsers',
        TrafficRow[]
    >;
};

export type TrafficRange = {
    from: string;
    to: string;
};
