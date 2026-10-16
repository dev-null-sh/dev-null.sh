import { LinkifiedText } from '@/components/linkified-text';
import { emojify } from '@/lib/emoji';
import { cn } from '@/lib/utils';
import type { BodySegment } from '@/types';

export function CommitBody({
    segments,
    className,
}: {
    segments: BodySegment[];
    className?: string;
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
                        return (
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
                            <div
                                key={index}
                                className="relative my-2 rounded-md border bg-muted/60"
                            >
                                {segment.language && (
                                    <span className="absolute top-1.5 right-2.5 font-mono text-[11px] text-muted-foreground select-none">
                                        {segment.language}
                                    </span>
                                )}
                                {/* The server escapes the code while highlighting it. */}
                                <pre
                                    className="overflow-x-auto px-3 py-2.5 font-mono text-[13px] leading-normal wrap-normal whitespace-pre"
                                    dangerouslySetInnerHTML={{
                                        __html: segment.html,
                                    }}
                                />
                            </div>
                        );
                }
            })}
        </div>
    );
}
