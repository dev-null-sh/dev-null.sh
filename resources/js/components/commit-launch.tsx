import { router, useHttp, usePage } from '@inertiajs/react';
import { Anchor, Check, Ship } from 'lucide-react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitLaunchController from '@/actions/App/Http/Controllers/CommitLaunchController';
import LaunchPassengerController from '@/actions/App/Http/Controllers/LaunchPassengerController';
import { LaunchShip } from '@/components/launch-ship';
import type { LaunchPassenger } from '@/components/launch-ship';
import { Button } from '@/components/ui/button';
import { useOwnsCommit } from '@/hooks/use-owns-commit';
import { signIn } from '@/lib/auth';
import { formatExactCount } from '@/lib/numbers';
import { coalesce, commitChannel, listen } from '@/lib/realtime';
import type { Commit } from '@/types';

type Crew = { count: number; boarded: boolean; passengers: LaunchPassenger[] };

function capacityMessage(count: number): string {
    if (count === 0) return 'A big little moment. Be the first aboard.';
    if (count < 4) return 'Plenty of room. Probably.';
    if (count < 8) return 'We planned for this. Mostly.';
    if (count < 16) return 'That is definitely within spec.';
    return 'Capacity planning has left the chat.';
}

export function CommitLaunch({ commit }: { commit: Commit }) {
    const { auth } = usePage().props;
    const ownsCommit = useOwnsCommit();
    const [crew, setCrew] = useState<Crew | null>(null);
    const [error, setError] = useState(false);
    const [retry, setRetry] = useState(0);
    const [element, setElement] = useState<HTMLDivElement | null>(null);
    const [visible, setVisible] = useState(false);
    const latest = useRef(0);
    const busy = useRef(false);
    const request = useHttp<Record<string, never>, Crew>({});
    const onIntersection = useEffectEvent(
        ([entry]: IntersectionObserverEntry[]) =>
            setVisible(entry.isIntersecting),
    );

    const read = useEffectEvent(async (signal?: AbortSignal) => {
        if (busy.current || document.hidden) return;
        const started = ++latest.current;
        try {
            const response = await fetch(
                CommitLaunchController.show.url(commit),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    cache: 'no-store',
                    signal,
                },
            );
            if (!response.ok) throw new Error('Could not load crew');
            const fresh = (await response.json()) as Crew;
            if (started === latest.current && !busy.current) {
                setCrew(fresh);
                setError(false);
            }
        } catch {
            if (!signal?.aborted && started === latest.current) setError(true);
        }
    });

    useEffect(() => {
        if (!element) return;
        const observer = new IntersectionObserver(onIntersection, {
            rootMargin: '100px',
        });
        observer.observe(element);
        return () => observer.disconnect();
    }, [element]);

    useEffect(() => {
        if (!visible) return;
        const controller = new AbortController();
        const refresh = coalesce(() => read(controller.signal), 300);
        refresh.request();
        const stop = listen(
            commitChannel(commit.id),
            { engagement: refresh.request, visibility: refresh.request },
            refresh.request,
        );
        const interval = window.setInterval(refresh.request, 20_000);
        const onVisible = () => {
            if (!document.hidden) refresh.request();
        };
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            stop();
            refresh.cancel();
            controller.abort();
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [visible, commit.id, auth.user?.id, retry]);

    const toggle = async () => {
        if (auth.user === null) {
            signIn();
            return;
        }
        if (
            !crew ||
            busy.current ||
            ownsCommit(commit) ||
            commit.status !== 'approved'
        )
            return;
        busy.current = true;
        ++latest.current;
        try {
            const fresh = await request.submit(
                crew.boarded
                    ? LaunchPassengerController.destroy(commit)
                    : LaunchPassengerController.store(commit),
            );
            ++latest.current;
            setCrew(fresh);
            setError(false);
            router.flushAll();
        } catch {
            toast.error('Could not update your boarding. Try again.');
        } finally {
            busy.current = false;
        }
    };
    const count = crew?.count ?? 0;

    return (
        <div
            ref={setElement}
            className="mt-3 overflow-hidden rounded-xl border bg-muted/15"
            data-test="commit-launch"
            onClick={(event) => event.stopPropagation()}
        >
            <div className="flex items-center justify-between gap-2 px-4 pt-3 font-mono text-xs">
                <span className="flex items-center gap-1.5 font-medium text-primary">
                    <Ship className="size-4" aria-hidden /> Shipped it
                </span>
                <span
                    role="status"
                    data-test="launch-count"
                    className="text-muted-foreground"
                >
                    {crew
                        ? `${formatExactCount(count)} aboard`
                        : 'Loading crew…'}
                </span>
            </div>
            <p className="px-4 pt-1 font-mono text-[11px] text-muted-foreground">
                Captain @{commit.author.handle}
            </p>
            <LaunchShip
                count={count}
                passengers={crew?.passengers ?? []}
                captain={commit.author}
            />
            <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
                <p
                    className="min-w-0 flex-1 text-xs text-muted-foreground"
                    data-test="launch-capacity"
                >
                    {capacityMessage(count)}
                </p>
                {ownsCommit(commit) ? (
                    <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Anchor className="size-3.5" aria-hidden /> You’re the
                        captain
                    </span>
                ) : (
                    <Button
                        type="button"
                        size="sm"
                        variant={crew?.boarded ? 'outline' : 'default'}
                        onClick={() => void toggle()}
                        aria-pressed={crew?.boarded ?? false}
                        aria-label={
                            crew?.boarded ? 'Step ashore' : 'All aboard'
                        }
                        disabled={
                            request.processing ||
                            (auth.user !== null && crew === null) ||
                            commit.status !== 'approved'
                        }
                        data-test="launch-board-button"
                    >
                        {crew?.boarded ? (
                            <Check className="size-4" aria-hidden />
                        ) : (
                            <Anchor className="size-4" aria-hidden />
                        )}
                        {request.processing
                            ? 'One moment…'
                            : crew?.boarded
                              ? 'You’re aboard'
                              : 'All aboard'}
                    </Button>
                )}
            </div>
            {commit.status !== 'approved' && (
                <p className="px-4 pb-3 text-xs text-muted-foreground">
                    Boarding opens once this launch is approved.
                </p>
            )}
            {error && (
                <button
                    type="button"
                    onClick={() => setRetry((attempt) => attempt + 1)}
                    className="px-4 pb-3 text-left text-xs text-destructive underline"
                >
                    Could not refresh the crew. Try again.
                </button>
            )}
        </div>
    );
}
