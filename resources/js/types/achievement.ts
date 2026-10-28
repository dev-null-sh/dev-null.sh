export type AchievementBadge = {
    id: string;
    name: string;
    description: string;
    icon: string;
    unlocked: boolean;
    progress: number;
    target: number;
};

export type UnixRank = {
    rank: string;
    level: number;
    min_xp: number;
    description: string;
    achieved: boolean;
};

export type XpMetric = {
    count: number;
    multiplier: number;
    xp: number;
};

export type XpBreakdown = {
    commits: XpMetric;
    streak: XpMetric;
    stars_received: XpMetric;
    replies: XpMetric;
    stars_given: XpMetric;
    luddite: XpMetric;
    shell_shocked: XpMetric;
};

export type XpLogEntry = {
    source:
        | 'commits'
        | 'replies'
        | 'streak'
        | 'stars_received'
        | 'stars_given'
        | 'shell_shocked';
    count: number;
    xp: number;
};

export type XpLogDay = {
    date: string;
    xp: number;
    entries: XpLogEntry[];
};

export type Achievements = {
    level: number;
    rank: string;
    xp: number;
    next_level_xp: number | null;
    level_progress: number;
    xp_breakdown: XpBreakdown;
    ranks: UnixRank[];
    badges: AchievementBadge[];
};
