import { CodeBlock } from '@/components/code-block';
import { LinkifiedText } from '@/components/linkified-text';
import { emojify } from '@/lib/emoji';
import { cn } from '@/lib/utils';
import type { BodySegment } from '@/types';

export function CommitBody({
    segments,
    className,
    isStatic = false,
}: {
    segments: BodySegment[];
    className?: string;
    isStatic?: boolean;
}) {
    return (
        <div
            className={cn(
                'leading-relaxed [overflow-wrap:anywhere] break-words whitespace-pre-wrap',
                className,
            )}
        >
            {segments.map((segment, index) => {
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
        </div>
    );
}
