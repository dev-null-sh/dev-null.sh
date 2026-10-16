import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export function PageTabs({
    label,
    tabs,
    preserveScroll = false,
    prefetch = true,
}: {
    label: string;
    preserveScroll?: boolean;
    prefetch?: boolean;
    tabs: {
        title: string;
        href: NonNullable<InertiaLinkProps['href']>;
        isActive: boolean;
    }[];
}) {
    return (
        <nav aria-label={label} className="flex border-b">
            {tabs.map((tab) => (
                <Link
                    key={tab.title}
                    href={tab.href}
                    prefetch={prefetch}
                    viewTransition
                    preserveScroll={preserveScroll}
                    aria-current={tab.isActive ? 'page' : undefined}
                    className={cn(
                        'flex flex-1 justify-center text-[15px] font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        { 'text-foreground': tab.isActive },
                    )}
                >
                    <span
                        className={cn('py-3.5', {
                            'shadow-[inset_0_-3px_0_var(--primary)]':
                                tab.isActive,
                        })}
                    >
                        {tab.title}
                    </span>
                </Link>
            ))}
        </nav>
    );
}
