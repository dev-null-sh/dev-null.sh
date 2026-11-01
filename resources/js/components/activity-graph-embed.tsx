import type { LucideIcon } from 'lucide-react';
import { Check, Copy, Monitor, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useUser } from '@/hooks/use-user';
import { cn } from '@/lib/utils';

type GraphTheme = 'light' | 'dark' | 'auto';
type EmbedTab = 'graph' | 'badge';

const themes: { value: GraphTheme; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'auto', icon: Monitor, label: 'Auto' },
];

function themedUrl(url: string, theme: GraphTheme): string {
    return theme === 'light' ? url : `${url}?theme=${theme}`;
}

export default function ActivityGraphEmbed({
    activityGraphUrl,
    badgeUrl,
    profileUrl,
}: {
    activityGraphUrl: string;
    badgeUrl?: string;
    profileUrl: string;
}) {
    const { user } = useUser();
    const [, copy] = useClipboard();
    const [tab, setTab] = useState<EmbedTab>('graph');
    const [theme, setTheme] = useState<GraphTheme>('light');
    const [isCopied, setIsCopied] = useState(false);

    const resolvedBadgeUrl =
        badgeUrl ?? activityGraphUrl.replace(/\/graph\.svg$/, '/badge.svg');

    const graphImageUrl = themedUrl(activityGraphUrl, theme);
    const graphSnippet = `[![/dev/null activity](${graphImageUrl})](${profileUrl})`;
    const graphPreviewUrl = `${graphImageUrl}${theme === 'light' ? '?' : '&'}lane=${encodeURIComponent(user.lane)}`;

    const badgeSvgUrl = themedUrl(resolvedBadgeUrl, theme);
    const badgeSnippet = `[![Follow @${user.handle} on /dev/null](${badgeSvgUrl})](${profileUrl})`;
    const badgePreviewUrl = `${badgeSvgUrl}${theme === 'light' ? '?' : '&'}lane=${encodeURIComponent(user.lane)}`;

    const currentSnippet = tab === 'graph' ? graphSnippet : badgeSnippet;

    useEffect(() => {
        if (!isCopied) {
            return;
        }

        const timeout = setTimeout(() => setIsCopied(false), 2000);

        return () => clearTimeout(timeout);
    }, [isCopied]);

    const copySnippet = async () => {
        if (await copy(currentSnippet)) {
            setIsCopied(true);
            toast(
                <span className="font-mono text-[13px]">
                    Copied the Markdown. Paste it into your README.
                </span>,
            );

            return;
        }

        toast.error(
            <span className="font-mono text-[13px]">
                fatal: could not copy the Markdown
            </span>,
        );
    };

    const CopyIcon = isCopied ? Check : Copy;

    return (
        <section className="px-4 py-6 sm:px-6" data-test="activity-graph-embed">
            <Heading
                title={
                    tab === 'graph'
                        ? 'Embed your activity graph'
                        : 'Embed your follow badge'
                }
                description={
                    tab === 'graph'
                        ? 'Your commit graph as an SVG for your GitHub profile README, or anywhere Markdown renders. It only shows approved commits and refreshes about once an hour.'
                        : 'A compact badge for your GitHub profile README or repository header. When clicked, it opens your /dev/null profile to follow you.'
                }
            />

            <div
                role="tablist"
                aria-label="Embed type"
                className="mb-5 inline-flex gap-1 rounded-lg border p-1"
            >
                <button
                    type="button"
                    role="tab"
                    aria-selected={tab === 'graph'}
                    onClick={() => setTab('graph')}
                    className={cn(
                        'flex items-center gap-2 rounded-md px-3.5 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        {
                            'bg-primary-soft text-primary hover:bg-primary-soft hover:text-primary':
                                tab === 'graph',
                        },
                    )}
                    data-test="graph-embed-tab"
                >
                    Activity graph
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={tab === 'badge'}
                    onClick={() => setTab('badge')}
                    className={cn(
                        'flex items-center gap-2 rounded-md px-3.5 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        {
                            'bg-primary-soft text-primary hover:bg-primary-soft hover:text-primary':
                                tab === 'badge',
                        },
                    )}
                    data-test="badge-embed-tab"
                >
                    Follow badge
                </button>
            </div>

            <div className="mb-5">
                <span className="block text-xs font-medium text-muted-foreground">
                    Theme
                </span>
                <div
                    role="radiogroup"
                    aria-label="Theme"
                    className="mt-1.5 inline-flex gap-1 rounded-lg border p-1"
                >
                    {themes.map(({ value, icon: Icon, label }) => (
                        <button
                            key={value}
                            type="button"
                            role="radio"
                            aria-checked={theme === value}
                            onClick={() => setTheme(value)}
                            className={cn(
                                'flex items-center gap-2 rounded-md px-3.5 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                                {
                                    'bg-primary-soft text-primary hover:bg-primary-soft hover:text-primary':
                                        theme === value,
                                },
                            )}
                        >
                            <Icon className="size-4" />
                            {label}
                        </button>
                    ))}
                </div>
                <p className="mt-2 text-sm text-muted-foreground">
                    {theme === 'auto'
                        ? 'Follows the color scheme of whoever is reading.'
                        : `Always ${theme}, whatever the reader prefers.`}
                </p>
            </div>

            {tab === 'graph' ? (
                <div className="mt-5 overflow-hidden rounded-lg border bg-background p-3 sm:p-4">
                    <img
                        src={graphPreviewUrl}
                        alt={`Commit activity graph of @${user.handle}`}
                        width={754}
                        height={196}
                        className="h-auto w-full"
                        data-test="activity-graph-preview"
                    />
                </div>
            ) : (
                <div className="mt-5 rounded-lg border bg-background">
                    <div className="flex h-9 items-center border-b px-4 font-mono text-[13px] text-muted-foreground">
                        Preview
                    </div>
                    <div className="flex items-center justify-center p-6 sm:p-8">
                        <img
                            src={badgePreviewUrl}
                            alt={`Follow badge of @${user.handle}`}
                            height={20}
                            className="h-5"
                            data-test="badge-preview"
                        />
                    </div>
                </div>
            )}

            <div className="mt-5 rounded-lg border">
                <div className="flex items-center justify-between gap-3 border-b py-1.5 pr-1.5 pl-4">
                    <span className="font-mono text-[13px] text-muted-foreground">
                        README.md
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={copySnippet}
                        data-test="copy-embed-button"
                    >
                        <CopyIcon />
                        {isCopied ? 'Copied' : 'Copy Markdown'}
                    </Button>
                </div>
                <pre className="overflow-x-auto px-4 py-3 font-mono text-[13px] break-all whitespace-pre-wrap">
                    <code data-test="embed-snippet">{currentSnippet}</code>
                </pre>
            </div>
        </section>
    );
}
