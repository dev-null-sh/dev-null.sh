import { Head } from '@inertiajs/react';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import { dropConfetti } from '@/lib/confetti';

type WrappedStats = {
    year: number;
    commits: number;
    replies: number;
    starsEarned: number;
    followersGained: number;
    activeDays: number;
    longestStreak: number;
    months: number[];
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

export default function Show({
    handle,
    name,
    avatarUrl,
    stats,
    shareUrl,
}: WrappedProps) {
    const [slide, setSlide] = useState(0);
    const [copied, copy] = useClipboard();
    const celebrated = useRef(false);

    const last = 5;
    const go = useCallback(
        (next: number) => setSlide(Math.min(last, Math.max(0, next))),
        [last],
    );

    useEffect(() => {
        if (slide === last && !celebrated.current) {
            celebrated.current = true;
            dropConfetti();
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
                    className="mt-12 flex min-h-[480px] flex-col justify-center rounded-xl border bg-card p-8 sm:p-12"
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
                            <Eyebrow>05 · Share</Eyebrow>
                            <h2 className="mt-6 text-3xl font-bold tracking-tight">
                                Take it with you
                            </h2>
                            <p className="mt-3 text-muted-foreground">
                                This page is public. Send it anywhere.
                            </p>
                            <p className="mx-auto mt-6 max-w-md rounded-md bg-muted px-3 py-2 font-mono text-xs wrap-anywhere">
                                {shareUrl}
                            </p>
                            <Button
                                className="mt-4"
                                onClick={() => copy(shareUrl)}
                                data-test="wrapped-copy-link"
                            >
                                {copied ? 'Copied' : 'Copy link'}
                            </Button>
                        </div>
                    )}
                </div>

                <div className="mt-10 flex items-center justify-between">
                    {slide > 0 ? (
                        <Button variant="outline" onClick={() => go(slide - 1)}>
                            Back
                        </Button>
                    ) : (
                        <span aria-hidden="true" className="w-[76px]" />
                    )}
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
                    {slide < last ? (
                        <Button onClick={() => go(slide + 1)}>Next</Button>
                    ) : (
                        <Button variant="outline" asChild>
                            <a href={`/@${handle}`}>View profile</a>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

Show.layout = AppLayout;
