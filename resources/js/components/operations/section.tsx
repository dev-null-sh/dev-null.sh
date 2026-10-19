import { useId } from 'react';
import type { ReactNode } from 'react';

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
                    <p className="font-mono text-[13px] text-muted-foreground">
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
