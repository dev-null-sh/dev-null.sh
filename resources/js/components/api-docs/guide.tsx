import type { ReactNode } from 'react';
import { CodeBlock } from '@/components/code-block';
import { cn } from '@/lib/utils';
import type { ApiDocsSnippet } from '@/types';

/**
 * How wide the docs' content is, guides and reference alike, so moving between them never changes the page's width.
 */
export const DOCS_WIDTH = 'max-w-[1160px]';

/**
 * A guide of the API docs, read like the site's other long pages: one Feed White sheet on the console's shell, as wide
 * as the reference, its sections one after the other with a hairline between them, never a card each.
 */
export function Guide({ children }: { children: ReactNode }) {
    return (
        <article
            className={cn(
                'mx-auto divide-y rounded-lg border bg-card text-[15px] leading-relaxed',
                DOCS_WIDTH,
            )}
        >
            {children}
        </article>
    );
}

function anchor(title: string): string {
    return title
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

/**
 * A section of a guide, its title a link to itself, so a reader can point someone at it.
 */
export function GuideSection({
    title,
    meta,
    children,
}: {
    title: string;
    meta?: ReactNode;
    children: ReactNode;
}) {
    const id = anchor(title);

    return (
        <section
            id={id}
            aria-labelledby={`${id}-title`}
            className="scroll-mt-32 space-y-3 px-5 py-7 text-pretty sm:px-8 lg:scroll-mt-20 [&_a]:underline [&_a]:decoration-rail [&_a]:underline-offset-4 [&_a:hover]:decoration-current"
        >
            <div className="flex flex-wrap items-baseline gap-x-3">
                <h2
                    id={`${id}-title`}
                    className="text-lg font-semibold tracking-tight"
                >
                    <a
                        href={`#${id}`}
                        className="no-underline! hover:underline!"
                    >
                        {title}
                    </a>
                </h2>
                {meta && (
                    <span className="font-mono text-[13px] text-muted-foreground">
                        {meta}
                    </span>
                )}
            </div>
            {children}
        </section>
    );
}

/**
 * A guide's code, in the code block the reference and the rest of the docs use, highlighted by the server.
 */
export function Snippet({ snippet }: { snippet: ApiDocsSnippet | undefined }) {
    if (snippet === undefined) {
        return null;
    }

    return (
        <CodeBlock
            html={snippet.html}
            language={snippet.language}
            filename={null}
        />
    );
}

export function Code({ children }: { children: ReactNode }) {
    return (
        <code className="rounded-sm bg-muted px-1 py-0.5 font-mono text-[13px]">
            {children}
        </code>
    );
}

/**
 * Named values in a hairline list, keys in mono, as the settings list what they show.
 */
export function FieldList({
    fields,
    keyWidth = 'sm:grid-cols-[9rem_minmax(0,1fr)]',
}: {
    fields: { name: string; children: ReactNode }[];
    keyWidth?: string;
}) {
    return (
        <dl className="divide-y rounded-lg border">
            {fields.map((field) => (
                <div
                    key={field.name}
                    className={`grid gap-x-3 gap-y-0.5 px-3 py-2.5 ${keyWidth}`}
                >
                    <dt className="font-mono text-[13px] text-muted-foreground">
                        {field.name}
                    </dt>
                    <dd className="min-w-0 text-sm">{field.children}</dd>
                </div>
            ))}
        </dl>
    );
}
