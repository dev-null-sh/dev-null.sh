import { useEffect, useRef, useState } from 'react';
import { CodeBlock } from '@/components/code-block';
import { CommitTranslationHint } from '@/components/commit-translation-hint';
import { LinkifiedText } from '@/components/linkified-text';
import { useCommitTranslation } from '@/hooks/use-commit-translation';
import { emojify } from '@/lib/emoji';
import { cn } from '@/lib/utils';
import type { BodySegment } from '@/types';

export function CommitBody({
    segments,
    className,
    isStatic = false,
    isTranslatable = false,
}: {
    segments: BodySegment[];
    className?: string;
    isStatic?: boolean;
    isTranslatable?: boolean;
}) {
    const translation = useCommitTranslation(
        segments,
        isTranslatable && !isStatic,
    );

    return (
        <div
            lang={
                translation.isTranslated
                    ? translation.target
                    : (translation.language ?? undefined)
            }
            className={cn(
                'leading-relaxed [overflow-wrap:anywhere] break-words whitespace-pre-wrap',
                className,
            )}
        >
            {translation.segments.map((segment, index) => {
                switch (segment.type) {
                    case 'text':
                        return isStatic ? (
                            emojify(segment.text)
                        ) : (
                            <LinkifiedText
                                key={index}
                                text={emojify(segment.text)}
                            />
                        );
                    case 'code':
                        return (
                            <code
                                key={index}
                                className="rounded-sm bg-muted px-1 py-0.5 font-mono text-[0.875em]"
                            >
                                {segment.code}
                            </code>
                        );
                    case 'block':
                        return (
                            <CodeBlock
                                key={index}
                                html={segment.html}
                                language={segment.language}
                                isCopyable={!isStatic}
                            />
                        );
                }
            })}

            {translation.canTranslate && (
                <CommitTranslationHint translation={translation} />
            )}
        </div>
    );
}

export function CommitBodyExcerpt({
    segments,
    className,
}: {
    segments: BodySegment[];
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
                isClamped && 'mask-b-from-60%',
            )}
        >
            <CommitBody segments={segments} className="text-[15px]" isStatic />
        </div>
    );
}
