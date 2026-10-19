import { BadgeCheck } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Quiet verified-via line with a gold badge, optional favicon, and https link to the domain.
 */
export function VerifiedDomain({
    domain,
    className,
}: {
    domain: string;
    className?: string;
}) {
    const href = `https://${domain}`;
    const favicon = `https://www.google.com/s2/favicons?domain=${encodeURIComponent(domain)}&sz=32`;

    return (
        <p
            className={cn(
                'flex min-w-0 items-center gap-1.5 font-mono text-[13px] text-muted-foreground',
                className,
            )}
        >
            <BadgeCheck
                className="size-3.5 shrink-0 fill-amber-400/25 text-amber-500 dark:fill-amber-400/20 dark:text-amber-400"
                aria-label="Verified"
            />
            <span className="shrink-0">Verified via</span>
            <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex min-w-0 items-center gap-1.5 text-foreground hover:underline"
            >
                <img
                    src={favicon}
                    alt=""
                    width={14}
                    height={14}
                    className="size-3.5 shrink-0 rounded-sm"
                />
                <span className="truncate">{domain}</span>
            </a>
        </p>
    );
}
