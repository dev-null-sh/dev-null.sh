import { CircleAlert, CircleCheck, CircleX } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SignalStatus } from '@/types';

const ICONS = { ok: CircleCheck, warn: CircleAlert, fail: CircleX };

export const STATUS_LABELS: Record<SignalStatus, string> = {
    ok: 'Healthy',
    warn: 'Watch',
    fail: 'Failing',
};

export function SignalIcon({
    status,
    className,
}: {
    status: SignalStatus;
    className?: string;
}) {
    const Icon = ICONS[status];

    return (
        <Icon
            aria-label={STATUS_LABELS[status]}
            className={cn(
                'size-4 shrink-0',
                {
                    'text-success': status === 'ok',
                    'text-warning': status === 'warn',
                    'text-destructive': status === 'fail',
                },
                className,
            )}
        />
    );
}
