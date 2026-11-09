import { Monitor, Moon, Sun } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitEmbedLinkController from '@/actions/App/Http/Controllers/CommitEmbedLinkController';
import { CodeBlock } from '@/components/code-block';
import { SegmentedControl } from '@/components/segmented-control';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn, shortHash } from '@/lib/utils';
import type { Commit } from '@/types';

type Theme = 'light' | 'dark' | 'auto';
type Format = 'html' | 'markdown' | 'link';
type Snippet = {
    code: string;
    block: { html: string; language: string | null };
};
type Links = {
    card: string;
    link: string;
    snippets: Record<Format, Snippet>;
};

const formats: Record<
    Format,
    {
        label: string;
        action: string;
        copied: string;
        noun: string;
        hint: string;
    }
> = {
    html: {
        label: 'HTML',
        action: 'Copy code',
        copied: 'copied',
        noun: 'embed code',
        hint: 'For any site that accepts HTML. The script adjusts the height.',
    },
    markdown: {
        label: 'Markdown',
        action: 'Copy Markdown',
        copied: 'Markdown copied',
        noun: 'Markdown',
        hint: "For READMEs and docs, which leave frames out: the commit's preview image, linked to the commit. Where Markdown accepts HTML, the HTML tab gives the live card.",
    },
    link: {
        label: 'Link',
        action: 'Copy link',
        copied: 'link copied',
        noun: 'link',
        hint: 'WordPress, Ghost, Discourse, Notion and other sites that support oEmbed turn this link into the card, with these settings.',
    },
};

function Switch({
    checked,
    onChange,
    labelledBy,
    describedBy,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    labelledBy: string;
    describedBy: string;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-labelledby={labelledBy}
            aria-describedby={describedBy}
            onClick={() => onChange(!checked)}
            className={cn(
                'mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent p-0.5 transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                checked ? 'bg-primary' : 'bg-muted-foreground/30',
            )}
        >
            <span
                className={cn(
                    'size-4.5 rounded-full bg-background shadow-sm transition-transform motion-reduce:transition-none',
                    checked && 'translate-x-5',
                )}
            />
        </button>
    );
}

function Section({
    title,
    description,
    control,
    children,
}: {
    title: string;
    description: ReactNode;
    control?: (ids: { label: string; description: string }) => ReactNode;
    children?: ReactNode;
}) {
    const id = useId();
    const ids = { label: `${id}-label`, description: `${id}-description` };

    return (
        <section className="grid gap-3.5 border-t px-6 py-5 first:border-t-0">
            <div className="flex items-start justify-between gap-6">
                <div className="min-w-0 space-y-1.5">
                    <h3 id={ids.label} className="text-base font-semibold">
                        {title}
                    </h3>
                    <p
                        id={ids.description}
                        className="text-sm text-pretty text-muted-foreground"
                    >
                        {description}
                    </p>
                </div>
                {control?.(ids)}
            </div>
            {children}
        </section>
    );
}

export function CommitEmbedDialog({
    commit,
    open,
    onOpenChange,
}: {
    commit: Commit;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isReply = commit.parent_id !== null;
    const hash = shortHash(commit.id);
    const [thread, setThread] = useState(true);
    const [replies, setReplies] = useState((commit.replies_count ?? 0) > 0);
    const [perLevel, setPerLevel] = useState(3);
    const [depth, setDepth] = useState<number | 'all'>(3);
    const [maxHeight, setMaxHeight] = useState<number | 'none'>(640);
    const [theme, setTheme] = useState<Theme>('auto');
    const [tab, setTab] = useState<Format>('html');
    const [links, setLinks] = useState<Links | null>(null);
    const [previewHeight, setPreviewHeight] = useState(240);
    const preview = useRef<HTMLIFrameElement>(null);
    const [, copy] = useClipboard();

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const query = {
            ...(thread && !isReply ? { thread: 1 } : {}),
            ...(replies ? { replies: perLevel, depth } : {}),
            ...(theme === 'auto' ? {} : { theme }),
            ...(maxHeight === 640 ? {} : { max_height: maxHeight }),
        };

        fetch(CommitEmbedLinkController.show.url(commit.id, { query }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) =>
                response.ok
                    ? (response.json() as Promise<Links>)
                    : Promise.reject(new Error(String(response.status))),
            )
            .then(setLinks)
            .catch((error: unknown) => {
                if (!controller.signal.aborted) {
                    toast.error(
                        `fatal: could not prepare the embed for ${hash}`,
                    );
                    console.warn(error);
                }
            });

        return () => controller.abort();
    }, [
        open,
        commit.id,
        hash,
        isReply,
        thread,
        replies,
        perLevel,
        depth,
        theme,
        maxHeight,
    ]);

    // The preview sizes itself from the card's messages, like embed.js does on other sites.
    useEffect(() => {
        const resize = (event: MessageEvent) => {
            const data = event.data as {
                type?: string;
                height?: unknown;
            } | null;

            if (
                event.origin === window.location.origin &&
                event.source === preview.current?.contentWindow &&
                data?.type === 'devnull:embed:resize' &&
                typeof data.height === 'number'
            ) {
                setPreviewHeight(Math.ceil(data.height));
            }
        };

        window.addEventListener('message', resize);

        return () => window.removeEventListener('message', resize);
    }, []);

    const snippet = links?.snippets[tab] ?? null;

    const copyCode = async () => {
        if (snippet !== null && (await copy(snippet.code))) {
            toast(
                <span className="font-mono text-[13px]">
                    [embed {hash}] {formats[tab].copied}
                </span>,
            );

            return;
        }

        toast.error(
            <span className="font-mono text-[13px]">
                fatal: could not copy {formats[tab].noun} for {hash}
            </span>,
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="sm:max-w-[880px]"
                data-test="commit-embed-dialog"
            >
                <DialogHeader className="text-left">
                    <DialogTitle>Embed commit</DialogTitle>
                    <DialogDescription>
                        Paste this code into your page. The card follows
                        /dev/null, so if the commit is deleted, it disappears
                        from your page too.
                    </DialogDescription>
                </DialogHeader>

                <div className="-mx-6 grid border-y md:grid-cols-[340px_minmax(0,1fr)]">
                    <div className="min-w-0">
                        {isReply ? (
                            <Section
                                title="Thread"
                                description={
                                    <>
                                        Starts from the root commit and goes
                                        down to this reply, like the commit's
                                        page.
                                    </>
                                }
                            />
                        ) : (
                            <Section
                                title="The rest of the thread"
                                description={`@${commit.author.handle}'s replies to their own commit, numbered 1/3, 2/3…`}
                                control={(ids) => (
                                    <Switch
                                        checked={thread}
                                        onChange={setThread}
                                        labelledBy={ids.label}
                                        describedBy={ids.description}
                                    />
                                )}
                            />
                        )}
                        <Section
                            title="Replies"
                            description="The first replies at each level, in order, the way threads open in your log."
                            control={(ids) => (
                                <Switch
                                    checked={replies}
                                    onChange={setReplies}
                                    labelledBy={ids.label}
                                    describedBy={ids.description}
                                />
                            )}
                        >
                            {replies && (
                                <div className="grid gap-2.5">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <span className="text-sm text-muted-foreground">
                                            Per level
                                        </span>
                                        <SegmentedControl
                                            label="Replies per level"
                                            name="embed-per-level"
                                            options={[1, 3, 5, 10].map(
                                                (count) => ({
                                                    value: count,
                                                    label: String(count),
                                                }),
                                            )}
                                            value={perLevel}
                                            onChange={setPerLevel}
                                            className="tabular-nums"
                                        />
                                    </div>
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <span className="text-sm text-muted-foreground">
                                            Levels
                                        </span>
                                        <SegmentedControl<number | 'all'>
                                            label="Levels of replies"
                                            name="embed-levels"
                                            options={[
                                                { value: 1, label: '1' },
                                                { value: 2, label: '2' },
                                                { value: 3, label: '3' },
                                                { value: 'all', label: 'All' },
                                            ]}
                                            value={depth}
                                            onChange={setDepth}
                                            className="tabular-nums"
                                        />
                                    </div>
                                </div>
                            )}
                        </Section>
                        <Section
                            title="Max height"
                            description="Longer threads scroll inside the card, and the /dev/null footer stays in view."
                        >
                            <SegmentedControl<number | 'none'>
                                label="Max height"
                                name="embed-max-height"
                                options={[
                                    { value: 480, label: '480px' },
                                    { value: 640, label: '640px' },
                                    { value: 800, label: '800px' },
                                    { value: 'none', label: 'None' },
                                ]}
                                value={maxHeight}
                                onChange={setMaxHeight}
                                className="w-fit tabular-nums"
                            />
                        </Section>
                        <Section
                            title="Theme"
                            description="Auto follows the reader's system, light by day and dark by night if that's how they have it set."
                        >
                            <SegmentedControl<Theme>
                                label="Theme"
                                name="embed-theme"
                                options={[
                                    {
                                        value: 'light',
                                        icon: Sun,
                                        label: 'Light',
                                    },
                                    {
                                        value: 'dark',
                                        icon: Moon,
                                        label: 'Dark',
                                    },
                                    {
                                        value: 'auto',
                                        icon: Monitor,
                                        label: 'Auto',
                                    },
                                ]}
                                value={theme}
                                onChange={setTheme}
                                className="w-fit"
                            />
                        </Section>
                    </div>
                    <div className="grid min-w-0 content-start gap-2 border-t bg-background px-6 py-5 md:border-t-0 md:border-l">
                        <span className="font-mono text-xs text-muted-foreground">
                            Preview
                        </span>
                        {links && (
                            <iframe
                                ref={preview}
                                src={links.card}
                                title={`Preview of the embed of ${hash}`}
                                className="w-full max-w-[550px] border-0"
                                // A frame in the dark theme over a card in the normal scheme gets an opaque
                                // backdrop around its rounded corners.
                                style={{
                                    height: previewHeight,
                                    colorScheme: 'normal',
                                }}
                            />
                        )}
                    </div>
                </div>

                <div className="grid min-w-0 gap-2">
                    <div
                        role="tablist"
                        aria-label="Embed format"
                        className="flex gap-5 border-b"
                    >
                        {(Object.keys(formats) as Format[]).map((value) => (
                            <button
                                key={value}
                                type="button"
                                role="tab"
                                aria-selected={tab === value}
                                onClick={() => setTab(value)}
                                className={cn(
                                    '-mb-px border-b-[3px] py-1.5 text-sm font-medium transition-colors',
                                    tab === value
                                        ? 'border-primary text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {formats[value].label}
                            </button>
                        ))}
                    </div>
                    <div data-test="commit-embed-code" className="min-w-0">
                        {snippet !== null && (
                            <CodeBlock
                                key={tab}
                                html={snippet.block.html}
                                language={snippet.block.language}
                                filename={null}
                            />
                        )}
                    </div>
                    <p className="text-[12.5px] text-muted-foreground">
                        {formats[tab].hint}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={() => void copyCode()}
                        disabled={links === null}
                    >
                        {formats[tab].action}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
