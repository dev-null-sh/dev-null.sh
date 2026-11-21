import type { ReactNode } from 'react';

export default function Heading({
    title,
    description,
}: {
    title: string;
    description?: ReactNode;
}) {
    return (
        <header className="mb-5">
            <h2 className="text-lg font-semibold tracking-tight">{title}</h2>
            {description && (
                <p className="mt-1 max-w-prose text-sm text-pretty text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}
