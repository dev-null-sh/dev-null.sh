import type { Activity } from './activity';

export type ActivityMetric = 'commits' | 'replies' | 'signups';

export type SiteActivity = Activity & { metric: ActivityMetric };

export type BreakdownRow = {
    name: string;
    count: number;
};

export type ActivityBreakdowns = Record<'statuses' | 'authors', BreakdownRow[]>;

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

export type Counter = 'signups' | 'commits' | 'replies' | 'authors';

export type Today = Record<Counter, { today: number; yesterday: number }>;

export type Overall = Record<Counter, number> & { since: string | null };

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

export type MetricsRange = TrafficRange & { all_time: boolean };

export type FunnelRate = 'conversion' | 'activation' | 'retention';

export type AcquisitionDay = {
    date: string;
    signups: number;
    visitors: number | null;
    converted: number | null;
    complete: boolean;
};

export type ActivationDay = {
    date: string;
    activated: number;
    eligible: number;
    complete: boolean;
};

export type Funnel = {
    visitors: number | null;
    signups: number;
    acquisition_by_day: AcquisitionDay[];
    activated: number;
    activation_eligible: number;
    activation_by_day: ActivationDay[];
    retained: number;
    retention_eligible: number;
    rates: Record<FunnelRate, number | null>;
    changes: Record<FunnelRate, number | null>;
};

export type ActiveUserCount = {
    date: string;
    count: number;
    returning: number;
    activated: number;
    not_activated: number;
    pending: number;
};

export type ActiveUsers = {
    daily: number;
    weekly: number;
    monthly: number;
    weekly_authors: number;
};

export type CommitResponses = {
    commits: number;
    answered: number;
    median_minutes: number | null;
    first_commits: number;
    first_answered: number;
};

export type NewcomerFollows = {
    newcomers: number;
    median_follows: number | null;
    follows: number;
    followed_back: number;
};

export type SignalStatus = 'ok' | 'warn' | 'fail';

export type SignalArea =
    | 'infrastructure'
    | 'queues'
    | 'moderation'
    | 'growth'
    | 'engagement';

export type Signal = {
    key: string;
    area: SignalArea;
    status: SignalStatus;
    title: string;
    detail: string;
    href: string | null;
};

export type DailyPoint = {
    date: string;
    signups: number;
    commits: number;
    replies: number;
    active_users: number;
    stars: number;
    follows: number;
    failed_jobs: number;
};

export type ForecastMetric = 'active_users' | 'signups' | 'commits' | 'replies';

export type ForecastSeries = {
    metric: ForecastMetric;
    method: 'flat' | 'linear' | 'seasonal';
    trend: number;
    today: number;
    history: { date: string; value: number }[];
    forecast: { date: string; value: number; lower: number; upper: number }[];
};

export type Milestone = {
    metric: 'users' | 'commits';
    current: number;
    target: number;
    eta: string | null;
};

export type MonthProjection = {
    metric: 'signups' | 'commits';
    month: string;
    to_date: number;
    projected: number;
    lower: number;
    upper: number;
};

export type OperationsEventLevel = 'info' | 'notice' | 'warning' | 'error';

export type OperationsEventSource =
    | 'signups'
    | 'jobs'
    | 'reports'
    | 'moderation'
    | 'bans'
    | 'timeouts'
    | 'features';

export type OperationsEvent = {
    id: string;
    source: OperationsEventSource;
    level: OperationsEventLevel;
    at: string;
    message: string;
    detail: string | null;
    actor: string | null;
    href: string | null;
};

export type OperationsEventWindow = '1h' | '24h' | '7d' | '30d';

export type OperationsEventFilters = {
    window: OperationsEventWindow;
    source: OperationsEventSource | null;
    level: OperationsEventLevel | null;
    search: string;
};

export type OperationsEventBucket = {
    at: string;
    info: number;
    notice: number;
    warning: number;
    error: number;
};

export type FailureTrend = {
    last_24h: number;
    previous_24h: number;
    hours: { at: string; count: number }[];
    by_job: { name: string; count: number; last_failed_at: string }[];
};

export type ModerationBacklog = {
    pending: number;
    oldest_pending_age: number | null;
    open_reports: number;
    active_timeouts: number;
};

export type Runtime = {
    environment: string;
    php: string;
    laravel: string;
    database: string;
    cache: string;
    queue: string;
};
