import AppLogoIcon from '@/components/app-logo-icon';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export function ContributorBadge({ className }: { className?: string }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    className={cn(
                        'inline-flex shrink-0 items-center self-center text-foreground',
                        className,
                    )}
                    data-test="contributor-badge"
                >
                    <AppLogoIcon
                        aria-hidden
                        className="size-3.5 fill-current"
                    />
                    <span className="sr-only">Contributor</span>
                </span>
            </TooltipTrigger>
            <TooltipContent>Contributor</TooltipContent>
        </Tooltip>
    );
}
