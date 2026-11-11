import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { CertificateText } from '@/components/certificate-text';
import { CodeBlock } from '@/components/code-block';
import { LinkifiedText } from '@/components/linkified-text';
import { bodyBlocks } from '@/lib/code';
import { emojify } from '@/lib/emoji';
import { cn } from '@/lib/utils';
import type { BodySegment, Commit } from '@/types';

export function CommitBody({
    segments,
    mentions,
    organizationMentions = null,
    className,
    isStatic = false,
    linksEveryMention = true,
}: {
    segments: BodySegment[];
    mentions: Commit['mentions'];
    organizationMentions?: Commit['organization_mentions'];
    className?: string;
    isStatic?: boolean;
    /**
     * Whether a mention of anyone else links to their page too, as in commits, or stays text, as in remotes.
     */
    linksEveryMention?: boolean;
}) {
    return (
        <div
            data-commit-body
            className={cn(
                'overflow-clip leading-relaxed wrap-break-word whitespace-pre-wrap [overflow-clip-margin:4px]',
                className,
            )}
        >
            {bodyBlocks(segments).map((block, index) =>
                block.quoted ? (
                    <blockquote
                        key={index}
                        className="my-1 min-h-[1lh] border-l-2 border-(--lane-5) pl-3"
                    >
                        <BodySegments
                            segments={block.segments}
                            mentions={mentions}
                            organizationMentions={organizationMentions}
                            isStatic={isStatic}
                            linksEveryMention={linksEveryMention}
                        />
                    </blockquote>
                ) : (
                    <BodySegments
                        key={index}
                        segments={block.segments}
                        mentions={mentions}
                        organizationMentions={organizationMentions}
                        isStatic={isStatic}
                        linksEveryMention={linksEveryMention}
                    />
                ),
            )}
        </div>
    );
}

/**
 * The block structure of a text segment: headings, subtext and lists render as blocks the way Discord does,
 * anything else stays a paragraph with the usual inline rendering.
 */
const headingPattern = /^(#{1,3}) (.+)$/;
const subtextPattern = /^-# (.+)$/;
const listItemPattern = /^([-*]|\d+\.) (.+)$/;

type TextBlock =
    | { kind: 'paragraph'; text: string }
    | { kind: 'heading'; level: number; text: string }
    | { kind: 'subtext'; text: string }
    | { kind: 'list'; ordered: boolean; start: number; items: string[] };

function splitTextBlocks(text: string): TextBlock[] {
    const blocks: TextBlock[] = [];
    let paragraph: string[] = [];
    let list: TextBlock | null = null;

    const flushParagraph = (): void => {
        if (paragraph.length > 0) {
            blocks.push({ kind: 'paragraph', text: paragraph.join('\n') });
            paragraph = [];
        }
    };

    const flushList = (): void => {
        if (list !== null) {
            blocks.push(list);
            list = null;
        }
    };

    for (const line of text.split('\n')) {
        const heading = headingPattern.exec(line);
        const subtext = subtextPattern.exec(line);
        const item = listItemPattern.exec(line);

        if (heading !== null) {
            flushParagraph();
            flushList();
            blocks.push({
                kind: 'heading',
                level: heading[1].length,
                text: heading[2],
            });
        } else if (subtext !== null) {
            flushParagraph();
            flushList();
            blocks.push({ kind: 'subtext', text: subtext[1] });
        } else if (item !== null) {
            flushParagraph();
            const ordered = /^\d/.test(item[1]);

            if (
                list === null ||
                list.kind !== 'list' ||
                list.ordered !== ordered
            ) {
                flushList();
                list = {
                    kind: 'list',
                    ordered,
                    start: ordered ? Number.parseInt(item[1], 10) : 1,
                    items: [],
                };
            }

            (list as { items: string[] }).items.push(item[2]);
        } else {
            flushList();
            paragraph.push(line);
        }
    }

    flushParagraph();
    flushList();

    return blocks;
}

function BodyTextBlocks({
    segmentKey,
    text,
    mentions,
    organizationMentions = null,
    pasteisMode,
    isStatic,
    linksEveryMention,
}: {
    segmentKey: number;
    text: string;
    mentions: Commit['mentions'];
    organizationMentions?: Commit['organization_mentions'];
    pasteisMode: boolean;
    isStatic: boolean;
    linksEveryMention: boolean;
}) {
    const shared = {
        mentions,
        organizationMentions,
        pasteisMode,
        isStatic,
        linksEveryMention,
    };

    return (
        <>
            {splitTextBlocks(text).map((block, index) => {
                const key = `block-${segmentKey}-${index}`;

                if (block.kind === 'heading') {
                    const Heading = `h${block.level}` as 'h1' | 'h2' | 'h3';

                    return (
                        <Heading
                            key={key}
                            className={
                                block.level === 1
                                    ? 'text-lg font-semibold'
                                    : block.level === 2
                                      ? 'text-base font-semibold'
                                      : 'text-sm font-semibold'
                            }
                        >
                            <LinkifiedText
                                key={key}
                                text={block.text}
                                {...shared}
                            />
                        </Heading>
                    );
                }

                if (block.kind === 'subtext') {
                    return (
                        <span
                            key={key}
                            className="block text-sm text-muted-foreground"
                        >
                            <LinkifiedText
                                key={key}
                                text={block.text}
                                {...shared}
                            />
                        </span>
                    );
                }

                if (block.kind === 'list') {
                    const items = block.items.map((item, itemIndex) => (
                        <li key={`${key}-${itemIndex}`}>
                            <LinkifiedText key={key} text={item} {...shared} />
                        </li>
                    ));

                    return block.ordered ? (
                        <ol
                            key={key}
                            start={block.start}
                            className="list-decimal pl-5"
                        >
                            {items}
                        </ol>
                    ) : (
                        <ul key={key} className="list-disc pl-5">
                            {items}
                        </ul>
                    );
                }

                return (
                    <LinkifiedText key={key} text={block.text} {...shared} />
                );
            })}
        </>
    );
}

export function BodySegments({
    segments,
    mentions,
    organizationMentions = null,
    isStatic = false,
    linksEveryMention = true,
}: {
    segments: BodySegment[];
    mentions: Commit['mentions'];
    organizationMentions?: Commit['organization_mentions'];
    isStatic?: boolean;
    linksEveryMention?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            {segments.map((segment, index) => {
                switch (segment.type) {
                    case 'text':
                        return (
                            <BodyTextBlocks
                                key={index}
                                segmentKey={index}
                                text={emojify(segment.text)}
                                mentions={mentions}
                                organizationMentions={organizationMentions}
                                pasteisMode={Boolean(auth.pasteis)}
                                isStatic={isStatic}
                                linksEveryMention={linksEveryMention}
                            />
                        );
                    case 'code':
                        return (
                            <code
                                key={index}
                                className="rounded-sm bg-muted px-1 py-0.5 font-mono text-[0.875em]"
                            >
                                <CertificateText text={segment.code} />
                            </code>
                        );
                    case 'block':
                        return (
                            <CodeBlock
                                key={index}
                                html={segment.html}
                                fullHtml={segment.fullHtml}
                                language={segment.language}
                                filename={segment.filename}
                                isCopyable={!isStatic}
                            />
                        );
                }
            })}
        </>
    );
}

/**
 * A body too long to show whole in a list comes with its first lines as a preview, unfolded on demand.
 */
export function FoldedCommitBody({
    segments,
    previewSegments,
    mentions,
    organizationMentions = null,
    className,
}: {
    segments: BodySegment[];
    previewSegments: BodySegment[] | null;
    mentions: Commit['mentions'];
    organizationMentions?: Commit['organization_mentions'];
    className?: string;
}) {
    const [isUnfolded, setIsUnfolded] = useState(false);
    const bodyRef = useRef<HTMLDivElement>(null);

    if (previewSegments === null) {
        return (
            <CommitBody
                segments={segments}
                mentions={mentions}
                organizationMentions={organizationMentions}
                className={className}
            />
        );
    }

    // The button goes away, so focus moves to what was unfolded: the commit card, which the keyboard shortcuts walk,
    // or the body itself inside a reply, which they don't.
    const unfold = (button: HTMLButtonElement) => {
        const card = button.closest<HTMLElement>('[data-reply], [data-commit]');

        setIsUnfolded(true);

        if (card?.matches('[data-commit]')) {
            card.focus({ preventScroll: true });
        } else {
            requestAnimationFrame(() =>
                bodyRef.current?.focus({ preventScroll: true }),
            );
        }
    };

    return (
        <div ref={bodyRef} tabIndex={-1} className="outline-none">
            <CommitBody
                segments={
                    isUnfolded
                        ? segments
                        : withFullBlocks(previewSegments, segments)
                }
                mentions={mentions}
                organizationMentions={organizationMentions}
                className={className}
            />
            {!isUnfolded && (
                <button
                    type="button"
                    onClick={(event) => unfold(event.currentTarget)}
                    className="mt-0.5 text-[15px] font-medium text-primary hover:underline"
                    data-test="show-more"
                >
                    Show more
                </button>
            )}
        </div>
    );
}

/**
 * The preview is the start of the body, so its code blocks are the body's first ones, in order. The last may be cut
 * to its first lines; each gets the whole block to copy.
 */
function withFullBlocks(
    previewSegments: BodySegment[],
    segments: BodySegment[],
): BodySegment[] {
    const blocks = segments.filter((segment) => segment.type === 'block');
    let index = 0;

    const preview = previewSegments.map((segment) =>
        segment.type === 'block'
            ? { ...segment, fullHtml: blocks[index++]?.html }
            : segment,
    );
    const last = preview.at(-1);
    const previous = preview.at(-2);

    // A faded block already shows that it continues; keep the ellipsis for completed blocks and prose.
    return last?.type === 'text' &&
        last.text.trim() === '…' &&
        previous?.type === 'block' &&
        previous.fullHtml !== undefined &&
        previous.fullHtml !== previous.html
        ? preview.slice(0, -1)
        : preview;
}

export function CommitBodyExcerpt({
    segments,
    previewSegments = null,
    className,
}: {
    segments: BodySegment[];
    previewSegments?: BodySegment[] | null;
    className?: string;
}) {
    const bodyRef = useRef<HTMLDivElement>(null);
    const [isClamped, setIsClamped] = useState(false);

    useEffect(() => {
        const body = bodyRef.current;

        if (!body) {
            return;
        }

        const observer = new ResizeObserver(() =>
            setIsClamped(body.scrollHeight > body.clientHeight),
        );
        observer.observe(body);

        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={bodyRef}
            className={cn(
                'overflow-hidden',
                className,
                previewSegments !== null
                    ? 'mask-b-from-[calc(100%-3rem)] mask-b-to-[calc(100%+0.75rem)]'
                    : isClamped && 'mask-b-from-60%',
            )}
        >
            <CommitBody
                segments={previewSegments ?? segments}
                mentions={null}
                className="flow-root text-[15px]"
                isStatic
            />
        </div>
    );
}
