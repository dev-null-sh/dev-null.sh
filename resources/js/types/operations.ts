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
