import { CircleAlert, CircleCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Status({
    isUp,
    children,
}: {
    isUp: boolean;
    children: ReactNode;
}) {
    const Icon = isUp ? CircleCheck : CircleAlert;

    return (
        <span
            className={cn('inline-flex h-5 items-center gap-1.5', {
                'font-medium text-destructive': !isUp,
            })}
        >
            <Icon
                aria-hidden
                className={cn(
                    'size-4 shrink-0',
                    isUp && 'text-muted-foreground',
                )}
            />
            {children}
        </span>
    );
}
