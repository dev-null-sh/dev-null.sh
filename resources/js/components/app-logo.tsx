import AppLogoIcon from '@/components/app-logo-icon';
import AppWordmark from '@/components/app-wordmark';
import SantaHat from '@/components/santa-hat';
import { cn } from '@/lib/utils';

export default function AppLogo({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 text-xl font-semibold tracking-tight',
                className,
            )}
        >
            <span className="relative inline-flex">
                <AppLogoIcon className="size-[1.1em] fill-current" />
                <SantaHat className="absolute -top-[0.2em] -right-[0.35em] hidden w-[0.85em] rotate-12 drop-shadow-[0_0_1px_rgba(0,0,0,0.4)] [:root[data-event-theme='christmas']_&]:block" />
            </span>
            <AppWordmark />
        </span>
    );
}
