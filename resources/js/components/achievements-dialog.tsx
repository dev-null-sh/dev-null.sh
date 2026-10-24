import { router } from '@inertiajs/react';
import {
    Bomb,
    BotOff,
    Box,
    Cookie,
    FileCode,
    Flame,
    GitMerge,
    Key,
    Server,
    Shield,
    Sparkles,
    Star,
    Terminal,
    Zap,
} from 'lucide-react';
import type { CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { WordmarkText } from '@/components/wordmark-text';
import { dropConfetti } from '@/lib/confetti';
import { formatCount, formatExactCount } from '@/lib/numbers';
import { cn, formatFullDate, laneTint } from '@/lib/utils';
import type {
    Achievements,
    Profile,
    User,
    XpLogDay,
    XpLogEntry,
} from '@/types';

const iconMap: Record<string, React.ComponentType<{ className?: string }>> = {
    terminal: Terminal,
    'file-code': FileCode,
    flame: Flame,
    zap: Zap,
    server: Server,
    box: Box,
    shield: Shield,
    star: Star,
    sparkles: Sparkles,
    'git-merge': GitMerge,
    key: Key,
    cookie: Cookie,
    'bot-off': BotOff,
    bomb: Bomb,
};

const xpSources: Record<XpLogEntry['source'], [string, string]> = {
    commits: ['commit', 'commits'],
    replies: ['reply to someone else', 'replies to others'],
    streak: ['streak day', 'streak days'],
    stars_received: ['star received', 'stars received'],
    stars_given: ['star given', 'stars given'],
    shell_shocked: ['shell shocked bonus', 'shell shocked bonus'],
};

function XpLogSkeleton() {
    return (
        <div
            aria-hidden
            className="space-y-3 motion-safe:animate-in motion-safe:delay-300 motion-safe:fill-mode-backwards motion-safe:fade-in"
        >
            {[0, 1, 2].map((index) => (
                <div key={index}>
                    <div className="flex items-center justify-between border-b pb-1">
                        <Skeleton className="h-4 w-20" />
                        <Skeleton className="h-4 w-12" />
                    </div>
                    <div className="mt-1 space-y-0.5">
                        <Skeleton className="h-4 w-2/3" />
                        <Skeleton className="h-4 w-1/2" />
                    </div>
                </div>
            ))}
        </div>
    );
}

export function AchievementsDialog({
    open,
    onOpenChange,
    achievements,
    xpLog,
    user,
    lane,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    achievements: Achievements;
    xpLog?: XpLogDay[];
    user: User | Profile;
    lane: string;
}) {
    const [tab, setTab] = useState<'badges' | 'ranks' | 'xp'>('badges');
    const [hasFailed, setHasFailed] = useState(false);

    // The log is only loaded once someone looks at it, so a profile visit stays as light as before.
    useEffect(() => {
        if (open && tab === 'xp' && xpLog === undefined) {
            router.reload({
                only: ['xpLog'],
                onStart: () => setHasFailed(false),
                onError: () => setHasFailed(true),
            });
        }
    }, [open, tab, xpLog]);

    const unlockedCount = achievements.badges.filter((b) => b.unlocked).length;
    const nextRank = achievements.ranks.find(
        (r) => r.level === achievements.level + 1,
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[85vh] overflow-y-auto sm:max-w-xl"
                style={{ '--lane': lane } as CSSProperties}
            >
                <DialogHeader className="text-left">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <DialogTitle className="text-base font-semibold">
                            Developer Achievements
                        </DialogTitle>
                        <span
                            className="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 font-mono text-xs font-medium text-foreground"
                            style={{
                                borderColor: laneTint(lane, 25),
                                backgroundColor: laneTint(lane, 8),
                            }}
                        >
                            Lv. {achievements.level} · {achievements.rank}
                        </span>
                    </div>
                    <DialogDescription className="font-mono text-xs text-muted-foreground">
                        @{user.username} ·{' '}
                        <span title={`${formatExactCount(achievements.xp)} XP`}>
                            {formatCount(achievements.xp)} XP
                        </span>{' '}
                        · {unlockedCount}/{achievements.badges.length} badges
                        unlocked
                    </DialogDescription>
                </DialogHeader>

                {/* Rank XP Progress */}
                <div className="rounded-lg border bg-muted/30 p-3 font-mono text-xs">
                    <div className="flex items-center justify-between text-muted-foreground">
                        <span
                            title={
                                achievements.next_level_xp
                                    ? `${formatExactCount(achievements.xp)} / ${formatExactCount(achievements.next_level_xp)} XP`
                                    : `${formatExactCount(achievements.xp)} XP`
                            }
                        >
                            {achievements.next_level_xp
                                ? `${formatCount(achievements.xp)} / ${formatCount(achievements.next_level_xp)} XP`
                                : `${formatCount(achievements.xp)} XP`}
                        </span>
                        <span
                            title={
                                nextRank
                                    ? `${formatExactCount(nextRank.min_xp - achievements.xp)} XP to ${nextRank.rank}`
                                    : undefined
                            }
                        >
                            {nextRank
                                ? `${formatCount(nextRank.min_xp - achievements.xp)} XP to ${nextRank.rank}`
                                : 'Maximum rank (UID 0)'}
                        </span>
                    </div>
                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full transition-all duration-500"
                            style={{
                                width: `${achievements.level_progress}%`,
                                backgroundColor: lane,
                            }}
                        />
                    </div>
                </div>

                {/* View Switcher */}
                <div className="flex border-b font-mono text-xs">
                    <button
                        type="button"
                        onClick={() => setTab('badges')}
                        className={cn(
                            'border-b-2 px-3 py-1.5 font-medium transition',
                            tab === 'badges'
                                ? 'border-(--lane) text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Badges ({achievements.badges.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab('ranks')}
                        className={cn(
                            'border-b-2 px-3 py-1.5 font-medium transition',
                            tab === 'ranks'
                                ? 'border-(--lane) text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Unix Ranks ({achievements.ranks.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab('xp')}
                        className={cn(
                            'border-b-2 px-3 py-1.5 font-medium transition',
                            tab === 'xp'
                                ? 'border-(--lane) text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        XP Log
                    </button>
                </div>

                {/* Badges Grid */}
                {tab === 'badges' && (
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {achievements.badges.map((badge) => {
                            const Icon = iconMap[badge.icon] ?? Terminal;
                            const className = cn(
                                'flex flex-col justify-between rounded-lg border p-3 text-left font-mono text-xs transition-colors',
                                badge.unlocked
                                    ? 'border-border bg-card shadow-xs hover:border-(--lane)'
                                    : 'border-dashed border-border/80 bg-muted/20 text-muted-foreground',
                            );

                            const body = (
                                <>
                                    <div>
                                        <div className="flex items-center justify-between gap-1.5">
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className={cn(
                                                        'flex size-6 shrink-0 items-center justify-center rounded border',
                                                        badge.unlocked
                                                            ? 'border-border bg-accent text-foreground'
                                                            : 'border-border/60 bg-muted text-muted-foreground/60',
                                                    )}
                                                >
                                                    <Icon className="size-3.5" />
                                                </div>
                                                <span className="font-semibold text-foreground">
                                                    {badge.name}
                                                </span>
                                            </div>
                                            <span
                                                className={cn(
                                                    'rounded px-1.5 py-0.5 text-[10px] font-medium',
                                                    badge.unlocked
                                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {badge.unlocked
                                                    ? '✓'
                                                    : `${badge.progress}/${badge.target}`}
                                            </span>
                                        </div>
                                        <p className="mt-1.5 font-sans text-xs leading-normal text-muted-foreground">
                                            <WordmarkText>
                                                {badge.description}
                                            </WordmarkText>
                                        </p>
                                    </div>

                                    {!badge.unlocked && badge.target > 1 && (
                                        <div className="mt-2.5 h-1 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                className="h-full rounded-full bg-muted-foreground/40 transition-all duration-300"
                                                style={{
                                                    width: `${Math.min(100, Math.round((badge.progress / badge.target) * 100))}%`,
                                                }}
                                            />
                                        </div>
                                    )}
                                </>
                            );

                            if (!badge.unlocked) {
                                return (
                                    <div key={badge.id} className={className}>
                                        {body}
                                    </div>
                                );
                            }

                            return (
                                <button
                                    key={badge.id}
                                    type="button"
                                    className={className}
                                    onClick={dropConfetti}
                                >
                                    {body}
                                </button>
                            );
                        })}
                    </div>
                )}

                {/* Unix Ranks Roadmap */}
                {tab === 'ranks' && (
                    <div className="space-y-2">
                        {achievements.ranks.map((rank) => {
                            const isCurrent = rank.level === achievements.level;
                            const className = cn(
                                'flex w-full items-center justify-between gap-3 rounded-lg border p-3 text-left font-mono text-xs transition-colors',
                                isCurrent
                                    ? 'border-(--lane) bg-card ring-1 ring-(--lane)'
                                    : rank.achieved
                                      ? 'border-border bg-card hover:border-(--lane)'
                                      : 'border-dashed border-border/60 bg-muted/20',
                            );
                            const body = (
                                <>
                                    <div className="flex items-center gap-2.5">
                                        <span
                                            className={cn(
                                                'flex size-6 shrink-0 items-center justify-center rounded text-xs font-bold',
                                                isCurrent
                                                    ? 'bg-accent text-foreground ring-1 ring-(--lane)'
                                                    : rank.achieved
                                                      ? 'bg-accent text-foreground'
                                                      : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {rank.level}
                                        </span>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-foreground">
                                                    {rank.rank}
                                                </span>
                                                {isCurrent && (
                                                    <span className="py-0.2 rounded bg-accent px-1.5 text-[10px] font-medium text-foreground">
                                                        CURRENT
                                                    </span>
                                                )}
                                            </div>
                                            <p className="font-sans text-[11px] text-muted-foreground">
                                                {rank.description}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="shrink-0 text-right">
                                        <span className="font-medium text-foreground">
                                            {formatCount(rank.min_xp)} XP
                                        </span>
                                        <div className="text-[10px] text-muted-foreground">
                                            {rank.achieved ? (
                                                <span className="text-emerald-700 dark:text-emerald-400">
                                                    ✓ Achieved
                                                </span>
                                            ) : (
                                                <span>
                                                    Requires{' '}
                                                    {formatCount(rank.min_xp)}{' '}
                                                    XP
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </>
                            );

                            if (!rank.achieved) {
                                return (
                                    <div key={rank.rank} className={className}>
                                        {body}
                                    </div>
                                );
                            }

                            return (
                                <button
                                    key={rank.rank}
                                    type="button"
                                    className={className}
                                    onClick={dropConfetti}
                                >
                                    {body}
                                </button>
                            );
                        })}
                    </div>
                )}

                {/* XP Log */}
                {tab === 'xp' && (
                    <div
                        className="min-h-40 font-mono text-xs"
                        aria-busy={xpLog === undefined && !hasFailed}
                    >
                        {hasFailed ? (
                            <p role="status" className="text-muted-foreground">
                                The XP log couldn't be loaded. Try again in a
                                minute.
                            </p>
                        ) : xpLog === undefined ? (
                            <XpLogSkeleton />
                        ) : xpLog.length === 0 ? (
                            <p role="status" className="text-muted-foreground">
                                No XP in the last 90 days.
                            </p>
                        ) : (
                            <ol className="space-y-3">
                                {xpLog.map((day) => (
                                    <li key={day.date}>
                                        <div className="flex items-center justify-between gap-2 border-b pb-1">
                                            <time
                                                dateTime={day.date}
                                                className="text-muted-foreground"
                                            >
                                                {formatFullDate(
                                                    `${day.date}T00:00:00`,
                                                )}
                                            </time>
                                            <span className="font-medium tabular-nums">
                                                +{formatExactCount(day.xp)} XP
                                            </span>
                                        </div>
                                        <ul className="mt-1 space-y-0.5">
                                            {day.entries.map((entry) => (
                                                <li
                                                    key={entry.source}
                                                    className="flex items-center justify-between gap-2 text-muted-foreground"
                                                >
                                                    <span>
                                                        {entry.count}{' '}
                                                        {
                                                            xpSources[
                                                                entry.source
                                                            ][
                                                                entry.count ===
                                                                1
                                                                    ? 0
                                                                    : 1
                                                            ]
                                                        }
                                                    </span>
                                                    <span className="tabular-nums">
                                                        +{entry.xp}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </div>
                )}

                {/* Dry Unix XP Formula Footnote */}
                <p className="border-t pt-2 text-center font-mono text-[11px] text-muted-foreground">
                    XP: 10/commit · 15/day streak · 5/star received · 5/reply ·
                    2/star given · −100/luddite · +100/shell shocked (minimum 0
                    XP)
                </p>
            </DialogContent>
        </Dialog>
    );
}
