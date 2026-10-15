import AppLogoIcon from '@/components/app-logo-icon';
import AppWordmark from '@/components/app-wordmark';
import { cn } from '@/lib/utils';

export default function AppLogo({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 text-xl font-semibold tracking-tight',
                className,
            )}
        >
            <AppLogoIcon className="size-[1.1em] fill-current" />
            <AppWordmark />
        </span>
    );
}
