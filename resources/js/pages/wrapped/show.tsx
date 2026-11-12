import { Head } from '@inertiajs/react';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import AppLogo from '@/components/app-logo';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import { dropFireworks } from '@/lib/confetti';

type WrappedStats = {
    year: number;
    commits: number;
    replies: number;
    starsEarned: number;
    followersGained: number;
    activeDays: number;
    longestStreak: number;
    months: number[];
    weekdays: number[];
    dayCounts: Record<string, number>;
    topCommit: { id: string; body: string; stars: number } | null;
};

type WrappedProps = {
    handle: string;
    name: string;
    avatarUrl: string | null;
    stats: WrappedStats;
    shareUrl: string;
};

function BigNumber({ value, label }: { value: number; label: string }) {
    return (
        <div>
            <p className="text-7xl font-bold tracking-tight tabular-nums">
                {value.toLocaleString()}
            </p>
            <p className="mt-3 text-sm text-muted-foreground">{label}</p>
        </div>
    );
}

function MonthBars({ months }: { months: number[] }) {
    const peak = Math.max(1, ...months);
    const level = (total: number): number =>
        total === 0 ? 0 : 1 + Math.min(3, Math.floor((total / peak) * 4));

    return (
        <div
            className="mx-auto mt-10 flex h-24 max-w-md items-end gap-1.5"
            role="img"
            aria-label="Commits per month"
        >
            {months.map((total, index) => (
                <div
                    key={index}
                    className="min-h-1 flex-1 rounded-sm"
                    style={{
                        height: `${Math.max(4, (total / peak) * 100)}%`,
                        backgroundColor:
                            level(total) === 0
                                ? 'var(--muted)'
                                : 'var(--lane-0)',
                        opacity: [1, 0.35, 0.55, 0.8, 1][level(total)],
                    }}
                    title={`${total} commits`}
                />
            ))}
        </div>
    );
}

function Eyebrow({ children }: { children: ReactNode }) {
    return (
        <p className="font-mono text-xs tracking-widest text-muted-foreground uppercase">
            {children}
        </p>
    );
}

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

function archetype(stats: WrappedStats): { name: string; quip: string } {
    if (stats.commits === 0) {
        return {
            name: 'Ghost in the Machine',
            quip: 'Present in spirit. The commits will come.',
        };
    }
    if (stats.longestStreak >= 30) {
        return {
            name: 'Streak Machine',
            quip: 'A month straight of shipping without blinking.',
        };
    }
    if (stats.starsEarned >= 100) {
        return {
            name: 'Star Magnet',
            quip: 'People keep starring whatever you touch.',
        };
    }
    if (stats.replies > stats.commits) {
        return {
            name: 'Conversation Starter',
            quip: 'Your best work happens in other people\u2019s threads.',
        };
    }
    if (stats.followersGained >= 25) {
        return {
            name: 'Rising Star',
            quip: 'The follower graph noticed before you did.',
        };
    }
    if (stats.commits >= 100) {
        return {
            name: 'Ship-it Machine',
            quip: 'Triple digits. Rest, then keep shipping.',
        };
    }
    return {
        name: 'Steady Builder',
        quip: 'Brick by brick. That is how codebases get built.',
    };
}

function buildInsights(stats: WrappedStats): string[] {
    const total = stats.months.reduce((sum, count) => sum + count, 0);

    if (total === 0) {
        return ['No commits this year. The graph is a blank canvas.'];
    }

    const best = stats.months.indexOf(Math.max(...stats.months));
    const weekend = (stats.weekdays[0] ?? 0) + (stats.weekdays[6] ?? 0);

    return [
        `Most productive month: ${MONTHS[best]} with ${stats.months[best]} commits.`,
        weekend / total >= 0.4
            ? 'Weekend warrior: nearly half your commits land on Sat/Sun.'
            : 'Weekday grinder: evenings and weekends stay yours.',
        stats.replies > stats.commits
            ? 'You reply more than you post. Threads love you.'
            : 'You post more than you reply. A builder first.',
    ];
}

function YearHeatmap({
    year,
    dayCounts,
}: {
    year: number;
    dayCounts: Record<string, number>;
}) {
    const peak = Math.max(1, ...Object.values(dayCounts));

    return (
        <div
            className="mx-auto mt-6 max-w-md"
            role="img"
            aria-label="Active days this year"
        >
            <div className="space-y-1">
                {MONTHS.map((label, month) => {
                    const total = new Date(year, month + 1, 0).getDate();
                    return (
                        <div key={label} className="flex items-center gap-1.5">
                            <span className="w-7 shrink-0 text-left font-mono text-[10px] text-muted-foreground uppercase">
                                {label}
                            </span>
                            <div className="grid flex-1 grid-cols-[repeat(31,minmax(0,1fr))] gap-[3px]">
                                {Array.from({ length: total }, (_, day) => {
                                    const key = `${year}-${String(month + 1).padStart(2, '0')}-${String(day + 1).padStart(2, '0')}`;
                                    const count = dayCounts[key] ?? 0;
                                    return (
                                        <span
                                            key={key}
                                            title={
                                                count === 0
                                                    ? `No commits on ${key}`
                                                    : `${count} commits on ${key}`
                                            }
                                            className="aspect-square rounded-[3px]"
                                            style={{
                                                backgroundColor:
                                                    count === 0
                                                        ? 'var(--muted)'
                                                        : 'var(--lane-0)',
                                                opacity:
                                                    count === 0
                                                        ? 1
                                                        : 0.35 +
                                                          (0.65 * count) / peak,
                                            }}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export default function Show({
    handle,
    name,
    avatarUrl,
    stats,
    shareUrl,
}: WrappedProps) {
    const [slide, setSlide] = useState(0);
    const [copiedLink, copyLink] = useClipboard();
    const [copiedSummary, copySummary] = useClipboard();
    const celebrated = useRef(false);

    const last = 6;
    const hasCommits = stats.commits > 0;
    const bestMonth = stats.months.indexOf(Math.max(...stats.months));
    const craft = archetype(stats);
    const insightList = buildInsights(stats);
    const summary = `My ${stats.year} on /dev/null - @${handle}: ${stats.commits} commits, ${stats.starsEarned} stars, best streak ${stats.longestStreak} days. ${shareUrl}`;
    const go = useCallback(
        (next: number) => setSlide(Math.min(last, Math.max(0, next))),
        [last],
    );

    useEffect(() => {
        if (slide === last && !celebrated.current) {
            celebrated.current = true;
            dropFireworks();
        }
    }, [slide, last]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent): void => {
            if (event.key === 'ArrowRight') {
                go(slide + 1);
            } else if (event.key === 'ArrowLeft') {
                go(slide - 1);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [slide, go]);

    return (
        <>
            <Head title={`${handle}'s ${stats.year} in review`} />
            <div className="mx-auto max-w-2xl px-4 py-16 text-center sm:py-24">
                <div
                    className="h-1 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-valuenow={slide + 1}
                    aria-valuemin={1}
                    aria-valuemax={last + 1}
                >
                    <div
                        className="h-full rounded-full transition-all"
                        style={{
                            width: `${((slide + 1) / (last + 1)) * 100}%`,
                            backgroundColor: 'var(--lane-0)',
                        }}
                    />
                </div>

                <div
                    className="mt-12 flex min-h-[640px] flex-col justify-center rounded-xl border bg-card p-8 sm:p-12"
                    data-test="wrapped-slide"
                >
                    {slide === 0 && (
                        <div>
                            <Eyebrow>
                                {stats.commits.toLocaleString()} contributions
                                in {stats.year}
                            </Eyebrow>
                            <Avatar className="mx-auto mt-6 size-20">
                                <AvatarImage
                                    src={avatarUrl ?? undefined}
                                    alt={name}
                                />
                                <AvatarFallback>
                                    {handle.slice(0, 2).toUpperCase()}
                                </AvatarFallback>
                            </Avatar>
                            <p className="mt-6 text-lg text-muted-foreground">
                                @{handle}
                            </p>
                            <h1 className="mt-2 text-5xl font-bold tracking-tight">
                                {stats.year} in review
                            </h1>
                            <p className="mt-4 text-muted-foreground">
                                {stats.commits === 0
                                    ? 'A quiet year. Next one ships more.'
                                    : `${stats.commits.toLocaleString()} commits shipped. Here is how they landed.`}
                            </p>
                        </div>
                    )}
                    {slide === 1 && (
                        <div>
                            <Eyebrow>01 · Output</Eyebrow>
                            <div className="mt-6">
                                <BigNumber
                                    value={stats.commits}
                                    label="commits shipped"
                                />
                            </div>
                            <p className="mt-4 text-muted-foreground">
                                plus {stats.replies.toLocaleString()} replies in
                                other people&apos;s threads
                            </p>
                            <MonthBars months={stats.months} />
                            {hasCommits && (
                                <>
                                    <p className="mt-4 text-sm text-muted-foreground">
                                        Best month: {MONTHS[bestMonth]} with{' '}
                                        {stats.months[bestMonth]} commits
                                    </p>
                                    <div className="mx-auto mt-4 grid max-w-md grid-cols-6 gap-1.5">
                                        {stats.months.map((total, index) => (
                                            <div
                                                key={MONTHS[index]}
                                                title={`${total} commits in ${MONTHS[index]}`}
                                                className="rounded-md border px-1 py-1.5"
                                                style={
                                                    index === bestMonth
                                                        ? {
                                                              backgroundColor:
                                                                  'var(--lane-0)',
                                                              borderColor:
                                                                  'transparent',
                                                          }
                                                        : undefined
                                                }
                                            >
                                                <p className="font-mono text-[10px] uppercase opacity-70">
                                                    {MONTHS[index]}
                                                </p>
                                                <p className="font-mono text-sm font-bold tabular-nums">
                                                    {total}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                    )}
                    {slide === 2 && (
                        <div>
                            <Eyebrow>02 · Impact</Eyebrow>
                            <div className="mt-6">
                                <BigNumber
                                    value={stats.starsEarned}
                                    label="stars earned"
                                />
                            </div>
                            {stats.topCommit !== null && (
                                <figure className="mx-auto mt-8 max-w-md rounded-lg border p-4 text-left">
                                    <blockquote className="text-sm">
                                        {stats.topCommit.body}
                                    </blockquote>
                                    <figcaption className="mt-2 text-xs text-muted-foreground">
                                        Your top commit ·{' '}
                                        {stats.topCommit.stars.toLocaleString()}{' '}
                                        stars
                                    </figcaption>
                                </figure>
                            )}
                        </div>
                    )}
                    {slide === 3 && (
                        <div>
                            <Eyebrow>03 · Consistency</Eyebrow>
                            <div className="mt-6">
                                <BigNumber
                                    value={stats.longestStreak}
                                    label="day streak at best"
                                />
                            </div>
                            <p className="mt-4 text-muted-foreground">
                                active on {stats.activeDays.toLocaleString()}{' '}
                                days this year
                            </p>
                            <YearHeatmap
                                year={stats.year}
                                dayCounts={stats.dayCounts}
                            />
                        </div>
                    )}
                    {slide === 4 && (
                        <div>
                            <Eyebrow>04 · Community</Eyebrow>
                            <div className="mt-6">
                                <BigNumber
                                    value={stats.followersGained}
                                    label="new followers this year"
                                />
                            </div>
                            <p className="mt-4 text-muted-foreground">
                                people who want to read what you ship next
                            </p>
                        </div>
                    )}
                    {slide === 5 && (
                        <div>
                            <Eyebrow>05 · Archetype</Eyebrow>
                            <h2 className="mt-6 text-4xl font-bold tracking-tight">
                                {craft.name}
                            </h2>
                            <p className="mt-3 text-muted-foreground">
                                {craft.quip}
                            </p>
                            <ul className="mx-auto mt-8 max-w-md space-y-2 text-left text-sm">
                                {insightList.map((insight) => (
                                    <li
                                        key={insight}
                                        className="rounded-lg border p-3"
                                    >
                                        {insight}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {slide === 6 && (
                        <div>
                            <Eyebrow>06 · Happy New Year</Eyebrow>
                            <div className="mt-6 flex justify-center">
                                <AppLogo className="text-3xl" />
                            </div>
                            <h2 className="mt-4 text-3xl font-bold tracking-tight">
                                See you next year, nuller
                            </h2>
                            <p className="mt-3 text-muted-foreground">
                                Thank you, nullers, for every commit, reply, and
                                star this year. From the team, happy New Year.
                            </p>
                            <p className="mt-3 text-sm text-muted-foreground">
                                This page is public. Send it anywhere.
                            </p>
                            <p className="mx-auto mt-6 max-w-md rounded-md bg-muted px-3 py-2 font-mono text-xs wrap-anywhere">
                                {shareUrl}
                            </p>
                            <p className="mx-auto mt-3 max-w-md rounded-md border p-3 text-left text-sm">
                                {summary}
                            </p>
                            <div className="mt-4 flex justify-center gap-2">
                                <Button
                                    onClick={() => copyLink(shareUrl)}
                                    data-test="wrapped-copy-link"
                                >
                                    {copiedLink ? 'Copied' : 'Copy link'}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => copySummary(summary)}
                                    data-test="wrapped-copy-summary"
                                >
                                    {copiedSummary ? 'Copied' : 'Copy summary'}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                <div className="mt-10 grid grid-cols-[1fr_auto_1fr] items-center">
                    <div className="flex justify-start">
                        {slide > 0 && (
                            <Button
                                variant="outline"
                                onClick={() => go(slide - 1)}
                            >
                                Back
                            </Button>
                        )}
                    </div>
                    <div className="flex gap-2" aria-hidden="true">
                        {Array.from({ length: last + 1 }, (_, index) => (
                            <span
                                key={index}
                                className="size-1.5 rounded-full"
                                style={{
                                    backgroundColor:
                                        index === slide
                                            ? 'var(--lane-0)'
                                            : 'var(--muted)',
                                }}
                            />
                        ))}
                    </div>
                    <div className="flex justify-end">
                        {slide < last ? (
                            <Button onClick={() => go(slide + 1)}>Next</Button>
                        ) : (
                            <Button variant="outline" asChild>
                                <a href={`/@${handle}`}>View profile</a>
                            </Button>
                        )}
                    </div>
                </div>
                <p className="mt-4 text-xs text-muted-foreground">
                    <kbd className="rounded border bg-muted px-1 font-mono">
                        ←
                    </kbd>{' '}
                    <kbd className="rounded border bg-muted px-1 font-mono">
                        →
                    </kbd>{' '}
                    to move through your year
                </p>
            </div>
        </>
    );
}

Show.layout = AppLayout;
