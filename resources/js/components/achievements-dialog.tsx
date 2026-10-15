import {
    Box,
    Code,
    FileCode,
    Flame,
    GitMerge,
    Maximize,
    Server,
    Shield,
    Sparkles,
    Star,
    Terminal,
    Zap,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import type { Achievements, Profile, User } from '@/types';

const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
    terminal: Terminal,
    flame: Flame,
    zap: Zap,
    box: Box,
    shield: Shield,
    star: Star,
    sparkles: Sparkles,
    'git-merge': GitMerge,
    maximize: Maximize,
    code: Code,
    'file-code': FileCode,
    server: Server,
};

export function AchievementsDialog({
    open,
    onOpenChange,
    achievements,
    user,
    lane,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    achievements: Achievements;
    user: User | Profile;
    lane: string;
}) {
    const [badgeFilter, setBadgeFilter] = useState<
        'all' | 'unlocked' | 'locked'
    >('all');
    const [activeTab, setActiveTab] = useState<'badges' | 'ranks' | 'xp'>(
        'badges',
    );

    const unlockedBadges = achievements.badges.filter((b) => b.unlocked);
    const lockedBadges = achievements.badges.filter((b) => !b.unlocked);

    const filteredBadges =
        badgeFilter === 'unlocked'
            ? unlockedBadges
            : badgeFilter === 'locked'
              ? lockedBadges
              : achievements.badges;

    const nextRank = achievements.ranks.find(
        (r) => r.level === achievements.level + 1,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"
                style={{ '--lane': lane } as CSSProperties}
            >
                <DialogHeader className="border-b pb-4 text-left">
                    <div className="flex items-start gap-3.5">
                        <UserAvatar
                            user={user}
                            className="size-12 text-base ring-2 ring-(--lane) ring-offset-2 ring-offset-background"
                        />
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <DialogTitle className="truncate text-lg font-semibold">
                                    {user.name}
                                </DialogTitle>
                                <span
                                    className="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 font-mono text-xs font-medium"
                                    style={{
                                        borderColor: `${lane}40`,
                                        backgroundColor: `${lane}15`,
                                        color: lane,
                                    }}
                                >
                                    <Shield className="size-3" />
                                    <span>
                                        Lv. {achievements.level} ·{' '}
                                        {achievements.rank}
                                    </span>
                                </span>
                            </div>
                            <DialogDescription className="mt-0.5 font-mono text-xs">
                                @{user.username} · {achievements.xp} Total XP ·{' '}
                                {unlockedBadges.length}/
                                {achievements.badges.length} Badges Unlocked
                            </DialogDescription>
                        </div>
                    </div>

                    {/* XP Progress Bar */}
                    <div className="mt-4 rounded-lg border bg-muted/30 p-3">
                        <div className="flex items-center justify-between font-mono text-xs text-muted-foreground">
                            <span>
                                {achievements.next_level_xp
                                    ? `${achievements.xp} / ${achievements.next_level_xp} XP`
                                    : `${achievements.xp} XP`}
                            </span>
                            <span>
                                {nextRank
                                    ? `${nextRank.min_xp - achievements.xp} XP until ${nextRank.rank}`
                                    : 'Superuser Access (UID 0)'}
                            </span>
                        </div>
                        <div className="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full transition-all duration-500"
                                style={{
                                    width: `${achievements.level_progress}%`,
                                    backgroundColor: lane,
                                }}
                            />
                        </div>
                    </div>

                    {/* Section Switcher Tabs */}
                    <div className="mt-4 flex gap-1 border-b border-border/60 pb-1 font-mono text-xs">
                        <button
                            type="button"
                            onClick={() => setActiveTab('badges')}
                            className={cn(
                                'rounded-md px-3 py-1.5 font-medium transition-colors',
                                activeTab === 'badges'
                                    ? 'bg-accent text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Badges ({achievements.badges.length})
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('ranks')}
                            className={cn(
                                'rounded-md px-3 py-1.5 font-medium transition-colors',
                                activeTab === 'ranks'
                                    ? 'bg-accent text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            Unix Ranks ({achievements.ranks.length})
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('xp')}
                            className={cn(
                                'rounded-md px-3 py-1.5 font-medium transition-colors',
                                activeTab === 'xp'
                                    ? 'bg-accent text-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            XP Breakdown
                        </button>
                    </div>
                </DialogHeader>

                {/* TAB 1: BADGES */}
                {activeTab === 'badges' && (
                    <div className="space-y-3.5">
                        <div className="flex items-center gap-1.5 font-mono text-xs">
                            <span className="text-muted-foreground">
                                Filter:
                            </span>
                            <button
                                type="button"
                                onClick={() => setBadgeFilter('all')}
                                className={cn(
                                    'rounded px-2 py-0.5 transition',
                                    badgeFilter === 'all'
                                        ? 'bg-foreground text-background'
                                        : 'bg-muted text-muted-foreground hover:text-foreground',
                                )}
                            >
                                All ({achievements.badges.length})
                            </button>
                            <button
                                type="button"
                                onClick={() => setBadgeFilter('unlocked')}
                                className={cn(
                                    'rounded px-2 py-0.5 transition',
                                    badgeFilter === 'unlocked'
                                        ? 'bg-foreground text-background'
                                        : 'bg-muted text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Unlocked ({unlockedBadges.length})
                            </button>
                            <button
                                type="button"
                                onClick={() => setBadgeFilter('locked')}
                                className={cn(
                                    'rounded px-2 py-0.5 transition',
                                    badgeFilter === 'locked'
                                        ? 'bg-foreground text-background'
                                        : 'bg-muted text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Locked ({lockedBadges.length})
                            </button>
                        </div>

                        <div className="grid gap-2.5 sm:grid-cols-2">
                            {filteredBadges.map((badge) => {
                                const Icon = iconMap[badge.icon] ?? Terminal;
                                const progressPct = Math.min(
                                    100,
                                    Math.round(
                                        (badge.progress / badge.target) * 100,
                                    ),
                                );

                                return (
                                    <div
                                        key={badge.id}
                                        className={cn(
                                            'relative flex flex-col justify-between rounded-lg border p-3 font-mono transition-colors',
                                            badge.unlocked
                                                ? 'border-border bg-card shadow-xs'
                                                : 'border-dashed border-border/80 bg-muted/20 text-muted-foreground',
                                        )}
                                    >
                                        <div>
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="flex items-center gap-2">
                                                    <div
                                                        className={cn(
                                                            'flex size-7 shrink-0 items-center justify-center rounded-md border',
                                                            badge.unlocked
                                                                ? 'border-border bg-accent text-foreground'
                                                                : 'border-border/60 bg-muted text-muted-foreground/60',
                                                        )}
                                                    >
                                                        <Icon className="size-4" />
                                                    </div>
                                                    <span className="text-xs font-semibold text-foreground">
                                                        {badge.name}
                                                    </span>
                                                </div>
                                                <span
                                                    className={cn(
                                                        'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                        badge.unlocked
                                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                            : 'bg-muted text-muted-foreground',
                                                    )}
                                                >
                                                    {badge.unlocked
                                                        ? '✓ Unlocked'
                                                        : 'Locked'}
                                                </span>
                                            </div>
                                            <p className="mt-2 font-sans text-xs leading-relaxed text-muted-foreground">
                                                {badge.description}
                                            </p>
                                        </div>

                                        <div className="mt-3">
                                            <div className="flex items-center justify-between text-[11px] text-muted-foreground">
                                                <span>Progress</span>
                                                <span>
                                                    {badge.progress} /{' '}
                                                    {badge.target} (
                                                    {progressPct}%)
                                                </span>
                                            </div>
                                            <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full transition-all duration-300"
                                                    style={{
                                                        width: `${progressPct}%`,
                                                        backgroundColor:
                                                            badge.unlocked
                                                                ? lane
                                                                : undefined,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* TAB 2: UNIX RANKS ROADMAP */}
                {activeTab === 'ranks' && (
                    <div className="space-y-2.5">
                        <p className="text-xs text-muted-foreground">
                            Unix permission ranks represent your standing in the
                            /dev/null system. Earn XP to climb from unprivileged
                            user space to root access.
                        </p>
                        <div className="space-y-2">
                            {achievements.ranks.map((rank) => {
                                const isCurrent =
                                    rank.level === achievements.level;

                                return (
                                    <div
                                        key={rank.rank}
                                        className={cn(
                                            'flex items-center justify-between gap-3 rounded-lg border p-3 font-mono text-xs transition-colors',
                                            isCurrent
                                                ? 'border-(--lane) bg-card ring-1 ring-(--lane)'
                                                : rank.achieved
                                                  ? 'border-border bg-card'
                                                  : 'border-dashed border-border/60 bg-muted/20 opacity-70',
                                        )}
                                    >
                                        <div className="flex items-center gap-3">
                                            <div
                                                className={cn(
                                                    'flex size-7 shrink-0 items-center justify-center rounded-md font-bold',
                                                    isCurrent
                                                        ? 'bg-(--lane) text-background'
                                                        : rank.achieved
                                                          ? 'bg-accent text-foreground'
                                                          : 'bg-muted text-muted-foreground',
                                                )}
                                            >
                                                {rank.level}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-semibold text-foreground">
                                                        {rank.rank}
                                                    </span>
                                                    {isCurrent && (
                                                        <span
                                                            className="py-0.2 rounded px-1.5 text-[10px] font-medium"
                                                            style={{
                                                                backgroundColor: `${lane}20`,
                                                                color: lane,
                                                            }}
                                                        >
                                                            CURRENT
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="font-sans text-[11px] text-muted-foreground">
                                                    {rank.description}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="text-right">
                                            <span className="font-medium text-foreground">
                                                {rank.min_xp} XP
                                            </span>
                                            <div className="text-[10px] text-muted-foreground">
                                                {rank.achieved ? (
                                                    <span className="text-emerald-600 dark:text-emerald-400">
                                                        Achieved
                                                    </span>
                                                ) : (
                                                    <span>
                                                        Requires {rank.min_xp}{' '}
                                                        XP
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* TAB 3: XP BREAKDOWN */}
                {activeTab === 'xp' && (
                    <div className="space-y-3 font-mono text-xs">
                        <p className="font-sans text-muted-foreground">
                            XP is awarded deterministically based on your
                            developer activity and community engagement on
                            /dev/null:
                        </p>

                        <div className="divide-y rounded-lg border bg-card">
                            <div className="flex items-center justify-between p-3">
                                <div className="flex items-center gap-2">
                                    <Terminal className="size-4 text-muted-foreground" />
                                    <span>Piped Commits</span>
                                </div>
                                <div className="text-right">
                                    <span className="font-medium text-foreground">
                                        +{achievements.xp_breakdown.commits.xp}{' '}
                                        XP
                                    </span>
                                    <p className="text-[10px] text-muted-foreground">
                                        {
                                            achievements.xp_breakdown.commits
                                                .count
                                        }{' '}
                                        commits × 10 XP
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center justify-between p-3">
                                <div className="flex items-center gap-2">
                                    <Flame className="size-4 text-muted-foreground" />
                                    <span>Longest Streak</span>
                                </div>
                                <div className="text-right">
                                    <span className="font-medium text-foreground">
                                        +{achievements.xp_breakdown.streak.xp}{' '}
                                        XP
                                    </span>
                                    <p className="text-[10px] text-muted-foreground">
                                        {achievements.xp_breakdown.streak.count}{' '}
                                        days × 15 XP
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center justify-between p-3">
                                <div className="flex items-center gap-2">
                                    <Sparkles className="size-4 text-muted-foreground" />
                                    <span>Stars Received</span>
                                </div>
                                <div className="text-right">
                                    <span className="font-medium text-foreground">
                                        +
                                        {
                                            achievements.xp_breakdown
                                                .stars_received.xp
                                        }{' '}
                                        XP
                                    </span>
                                    <p className="text-[10px] text-muted-foreground">
                                        {
                                            achievements.xp_breakdown
                                                .stars_received.count
                                        }{' '}
                                        stars × 5 XP
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center justify-between p-3">
                                <div className="flex items-center gap-2">
                                    <GitMerge className="size-4 text-muted-foreground" />
                                    <span>Thread Replies</span>
                                </div>
                                <div className="text-right">
                                    <span className="font-medium text-foreground">
                                        +{achievements.xp_breakdown.replies.xp}{' '}
                                        XP
                                    </span>
                                    <p className="text-[10px] text-muted-foreground">
                                        {
                                            achievements.xp_breakdown.replies
                                                .count
                                        }{' '}
                                        replies × 5 XP
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center justify-between p-3">
                                <div className="flex items-center gap-2">
                                    <Star className="size-4 text-muted-foreground" />
                                    <span>Stars Given</span>
                                </div>
                                <div className="text-right">
                                    <span className="font-medium text-foreground">
                                        +
                                        {
                                            achievements.xp_breakdown
                                                .stars_given.xp
                                        }{' '}
                                        XP
                                    </span>
                                    <p className="text-[10px] text-muted-foreground">
                                        {
                                            achievements.xp_breakdown
                                                .stars_given.count
                                        }{' '}
                                        stars × 2 XP
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-between rounded-lg border bg-muted/30 p-3 font-semibold text-foreground">
                            <span>Total XP</span>
                            <span style={{ color: lane }}>
                                {achievements.xp} XP
                            </span>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
