import { ChevronRight } from 'lucide-react';
import { Fragment, useState } from 'react';
import { WARNING } from '@/components/api-docs/tones';
import { cn } from '@/lib/utils';
import type { ApiDocsField } from '@/types';

/**
 * A description from the spec, with what it wraps in backticks set as code, as its docblock wrote it.
 */
export function SpecText({ text }: { text: string }) {
    return (
        <>
            {text.split(/(`[^`]+`)/).map((part, index) => (
                <Fragment key={index}>
                    {part.startsWith('`') && part.endsWith('`') ? (
                        <code className="rounded-sm bg-muted px-1 font-mono text-[12px] text-foreground">
                            {part.slice(1, -1)}
                        </code>
                    ) : (
                        part
                    )}
                </Fragment>
            ))}
        </>
    );
}

function FieldRow({ field }: { field: ApiDocsField }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <li className="px-3 py-2.5">
            <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <span className="font-mono text-[13px] font-medium">
                    {field.name}
                </span>
                <span className="font-mono text-[12px] text-muted-foreground">
                    {field.type}
                </span>
                {field.required && (
                    <span
                        className={cn(
                            'rounded-sm border px-1 font-mono text-[11px] leading-4 font-medium',
                            WARNING,
                        )}
                    >
                        required
                    </span>
                )}
            </div>
            {field.description && (
                <p className="mt-0.5 text-sm text-pretty text-muted-foreground">
                    <SpecText text={field.description} />
                </p>
            )}
            {field.enum && (
                <p className="mt-1 flex flex-wrap gap-1">
                    {field.enum.map((value) => (
                        <span
                            key={value}
                            className="rounded-sm bg-muted px-1 font-mono text-[12px]"
                        >
                            {value}
                        </span>
                    ))}
                </p>
            )}
            {field.children.length > 0 && (
                <>
                    <button
                        type="button"
                        onClick={() => setIsOpen(!isOpen)}
                        aria-expanded={isOpen}
                        className="mt-1 -ml-1 flex items-center gap-1 rounded-md px-1 py-0.5 text-sm text-primary hover:bg-accent"
                    >
                        <ChevronRight
                            aria-hidden
                            className={cn(
                                'size-3.5 transition-transform motion-reduce:transition-none',
                                isOpen && 'rotate-90',
                            )}
                        />
                        {isOpen ? 'Hide' : 'Show'} {field.children.length}{' '}
                        {field.children.length === 1 ? 'field' : 'fields'}
                    </button>
                    {isOpen && (
                        <FieldTable
                            fields={field.children}
                            className="mt-2 rounded-none border-0 border-l-2 border-rail"
                        />
                    )}
                </>
            )}
        </li>
    );
}

/**
 * What a request takes or an answer holds, field by field: its name and type in mono, whether it is required, what
 * it means, the values it may take, and the fields inside it, folded until asked for.
 */
export function FieldTable({
    fields,
    className,
}: {
    fields: ApiDocsField[];
    className?: string;
}) {
    return (
        <ul className={cn('divide-y rounded-lg border', className)}>
            {fields.map((field) => (
                <FieldRow key={field.name} field={field} />
            ))}
        </ul>
    );
}
