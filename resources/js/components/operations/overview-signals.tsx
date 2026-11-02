import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Panel } from '@/components/operations/section';
import { SignalIcon, STATUS_LABELS } from '@/components/operations/signal-icon';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import type { Signal, SignalStatus } from '@/types';

const SEVERITY: Record<SignalStatus, number> = { fail: 0, warn: 1, ok: 2 };

function count(signals: Signal[], status: SignalStatus): number {
    return signals.filter((signal) => signal.status === status).length;
}

/**
 * One line that says how /dev/null is doing: the failing signals if any, else the ones to watch, else all clear.
 */
export function SignalsHeadline({ signals }: { signals: Signal[] }) {
    const failing = count(signals, 'fail');
    const watching = count(signals, 'warn');
    const status: SignalStatus =
        failing > 0 ? 'fail' : watching > 0 ? 'warn' : 'ok';
    const attention = signals
        .filter((signal) => signal.status !== 'ok')
        .toSorted((a, b) => SEVERITY[a.status] - SEVERITY[b.status])
        .map((signal) => signal.title)
        .join(', ');

    return (
        <section
            aria-label="Overall status"
            className="flex flex-col gap-3 rounded-lg border bg-card px-4 py-3.5 sm:flex-row sm:items-center sm:gap-6"
        >
            <div className="flex min-w-0 flex-1 items-center gap-3">
                <SignalIcon status={status} className="size-6" />
                <div className="min-w-0">
                    <h2 className="text-lg leading-6 font-semibold tracking-tight">
                        {status === 'fail' && `${failing} failing`}
                        {status === 'warn' && `${watching} to watch`}
                        {status === 'ok' && 'All systems normal'}
                    </h2>
                    <p
                        className="truncate text-sm text-muted-foreground"
                        title={attention}
                    >
                        {status === 'ok'
                            ? `${formatCount(signals.length)} checks, nothing needs attention.`
                            : attention}
                    </p>
                </div>
            </div>
            <ul className="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-1 font-mono text-[13px] tabular-nums">
                {(['fail', 'warn', 'ok'] as const).map((item) => {
                    const total = count(signals, item);

                    return (
                        <li key={item} className="flex items-center gap-1.5">
                            <span aria-hidden>
                                <SignalIcon
                                    status={item}
                                    className={cn(
                                        total === 0 && 'text-muted-foreground',
                                    )}
                                />
                            </span>
                            <span
                                className={cn(
                                    'font-medium',
                                    total === 0 && 'text-muted-foreground',
                                )}
                            >
                                {formatCount(total)}
                            </span>
                            <span className="text-muted-foreground">
                                {STATUS_LABELS[item].toLowerCase()}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

function AreaTag({ area }: { area: string }) {
    return (
        <span className="shrink-0 rounded-sm border px-1.5 font-mono text-[11px] leading-5 text-muted-foreground">
            {area}
        </span>
    );
}

function SignalRow({ signal }: { signal: Signal }) {
    const content = (
        <>
            <SignalIcon status={signal.status} className="mt-0.5" />
            <span className="min-w-0 flex-1">
                <span className="flex items-center gap-2">
                    <span
                        className="min-w-0 truncate text-sm font-medium"
                        title={signal.title}
                    >
                        {signal.title}
                    </span>
                    <AreaTag area={signal.area} />
                </span>
                <span
                    className="block truncate font-mono text-[13px] text-muted-foreground"
                    title={signal.detail}
                >
                    {signal.detail}
                </span>
            </span>
            {signal.href && (
                <ChevronRight
                    aria-hidden
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                />
            )}
        </>
    );
    const className = 'flex items-start gap-3 px-4 py-2.5';

    return (
        <li>
            {signal.href ? (
                <Link
                    href={signal.href}
                    className={cn(
                        className,
                        'transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:-outline-offset-2',
                    )}
                >
                    {content}
                </Link>
            ) : (
                <div className={className}>{content}</div>
            )}
        </li>
    );
}

function HealthySignal({ signal }: { signal: Signal }) {
    const content = (
        <>
            <SignalIcon status={signal.status} className="mt-0.5" />
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm" title={signal.title}>
                    {signal.title}
                </span>
                <span
                    className="block truncate font-mono text-xs text-muted-foreground"
                    title={signal.detail}
                >
                    {signal.detail}
                </span>
            </span>
        </>
    );
    const className = 'flex items-start gap-2 rounded-md px-2 py-1.5';

    return (
        <li className="min-w-0">
            {signal.href ? (
                <Link
                    href={signal.href}
                    className={cn(
                        className,
                        'transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:-outline-offset-2',
                    )}
                >
                    {content}
                </Link>
            ) : (
                <div className={className}>{content}</div>
            )}
        </li>
    );
}

/**
 * Every signal, the failing and watched ones first as full rows, then the healthy ones as a compact grid by area.
 */
export function SignalsBoard({ signals }: { signals: Signal[] }) {
    const attention = signals
        .filter((signal) => signal.status !== 'ok')
        .toSorted((a, b) => SEVERITY[a.status] - SEVERITY[b.status]);
    const healthy = Object.entries(
        Object.groupBy(
            signals.filter((signal) => signal.status === 'ok'),
            (signal) => signal.area,
        ),
    );

    return (
        <Panel title="Signals" meta={`${formatCount(signals.length)} checks`}>
            {attention.length > 0 && (
                <ul className="-mx-4 -mt-4 mb-4 divide-y border-b">
                    {attention.map((signal) => (
                        <SignalRow key={signal.key} signal={signal} />
                    ))}
                </ul>
            )}
            {healthy.length === 0 ? (
                <p className="font-mono text-[13px] text-muted-foreground">
                    0 healthy
                </p>
            ) : (
                <div className="space-y-3">
                    <h3 className="font-mono text-[13px] text-muted-foreground">
                        {formatCount(count(signals, 'ok'))} healthy
                    </h3>
                    <div className="grid gap-x-4 gap-y-3 sm:grid-cols-2">
                        {healthy.map(([area, items]) => (
                            <div key={area} className="min-w-0">
                                <h4 className="px-2 pb-0.5 font-mono text-xs text-muted-foreground">
                                    {area}
                                </h4>
                                <ul>
                                    {items?.map((signal) => (
                                        <HealthySignal
                                            key={signal.key}
                                            signal={signal}
                                        />
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </Panel>
    );
}
