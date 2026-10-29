import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

export function PresetButton({
    isActive,
    onClick,
    children,
}: {
    isActive: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <Button
            variant={isActive ? 'secondary' : 'ghost'}
            size="sm"
            className="h-9 px-2 font-mono text-[13px] sm:h-7"
            aria-pressed={isActive}
            onClick={onClick}
        >
            {children}
        </Button>
    );
}
