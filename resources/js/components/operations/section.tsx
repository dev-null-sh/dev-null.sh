import { useId } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Section({
    title,
    meta,
    children,
}: {
    title: string;
    meta?: ReactNode;
    children: ReactNode;
}) {
    const id = useId();

    return (
        <section aria-labelledby={id} className="border-b px-4 py-5 sm:px-6">
            <div className="mb-3 flex items-baseline justify-between gap-3">
                <h2 id={id} className="font-semibold tracking-tight">
                    {title}
                </h2>
                {meta && (
                    <p className="font-mono text-[13px] text-muted-foreground tabular-nums">
                        {meta}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

export function Field({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] items-start gap-3 px-4 py-2.5 leading-5">
            <dt className="font-mono text-[13px] text-muted-foreground">
                {label}
            </dt>
            <dd className="min-w-0">{children}</dd>
        </div>
    );
}

/**
 * A hairline-bordered group on the console's grid: a title with optional mono meta and actions, then its content.
 * A filling panel stretches its content down to the bottom, as tall as the grid row it sits in.
 */
export function Panel({
    title,
    meta,
    actions,
    fill = false,
    className,
    children,
}: {
    title: string;
    meta?: ReactNode;
    actions?: ReactNode;
    fill?: boolean;
    className?: string;
    children: ReactNode;
}) {
    const id = useId();

    return (
        <section
            aria-labelledby={id}
            className={cn(
                'min-w-0 rounded-lg border bg-card',
                fill && 'flex flex-col',
                className,
            )}
        >
            <div className="flex min-h-12 flex-wrap items-center gap-x-3 gap-y-2 border-b px-4 py-2.5">
                <div className="flex min-w-0 flex-[1_1_14rem] items-baseline gap-x-3">
                    <h2
                        id={id}
                        className="shrink-0 font-semibold tracking-tight"
                    >
                        {title}
                    </h2>
                    {meta && (
                        <p
                            className="min-w-0 truncate font-mono text-[13px] text-muted-foreground tabular-nums"
                            title={typeof meta === 'string' ? meta : undefined}
                        >
                            {meta}
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="ml-auto flex shrink-0 items-center gap-1">
                        {actions}
                    </div>
                )}
            </div>
            <div className={cn('p-4', fill && 'flex flex-1 flex-col')}>
                {children}
            </div>
        </section>
    );
}
