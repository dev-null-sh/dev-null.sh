import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * The shell of a long-form page, such as the about page or the privacy policy: a sticky title bar, then sections.
 */
export function DocumentPage({
    title,
    meta,
    children,
}: {
    title: string;
    meta?: ReactNode;
    children: ReactNode;
}) {
    return (
        <>
            <header className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b bg-card px-4 sm:px-6">
                <h1 className="font-semibold">{title}</h1>
                {meta && (
                    <span className="font-mono text-[13px] text-muted-foreground">
                        {meta}
                    </span>
                )}
            </header>

            <article className="divide-y text-[15px] leading-relaxed">
                {children}
            </article>
        </>
    );
}

export function DocumentSection({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-title`}
            className="scroll-mt-14 space-y-3 px-4 py-6 text-pretty sm:px-6 [&_a]:underline [&_a]:decoration-rail [&_a]:underline-offset-4 [&_a:hover]:decoration-current"
        >
            <h2
                id={`${id}-title`}
                className="text-lg font-semibold tracking-tight"
            >
                {title}
            </h2>
            {children}
        </section>
    );
}

export function DocumentList({
    items,
    className,
}: {
    items: ReactNode[];
    className?: string;
}) {
    return (
        <ul className={cn('space-y-2', className)}>
            {items.map((item, index) => (
                <li key={index} className="flex gap-2.5">
                    <span
                        aria-hidden
                        className="mt-[0.6em] size-1.5 shrink-0 rounded-full bg-rail"
                    />
                    <span className="min-w-0">{item}</span>
                </li>
            ))}
        </ul>
    );
}
