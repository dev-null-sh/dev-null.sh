import { Link } from '@inertiajs/react';
import { ChevronRight, Lock } from 'lucide-react';
import { Fragment, useState, useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';
import ApiDocsController from '@/actions/App/Http/Controllers/ApiDocsController';
import ApiDocsSchemaController from '@/actions/App/Http/Controllers/ApiDocsSchemaController';
import { ExampleTabs } from '@/components/api-docs/example-tabs';
import { FieldTable, SpecText } from '@/components/api-docs/field-table';
import { loadSchemas, useSchemas } from '@/components/api-docs/schemas';
import type {
    ApiDocsSchema,
    ApiDocsSchemas,
    SchemasState,
} from '@/components/api-docs/schemas';
import { DESTRUCTIVE, SUCCESS, WARNING } from '@/components/api-docs/tones';
import { CodeBlock } from '@/components/code-block';
import { useIsLate } from '@/hooks/use-is-late';
import { cn } from '@/lib/utils';
import type { ApiDocsEndpoint, ApiDocsField, ApiDocsResponse } from '@/types';

/**
 * An error every endpoint may answer with, which the Errors guide describes once rather than each endpoint.
 */
export function isSharedError(response: ApiDocsResponse): boolean {
    return !response.status.startsWith('2') && response.fields.length === 0;
}

/**
 * A method by what it does: reading in green, writing in ochre, deleting in red. No blue, which only ever means
 * "act here".
 */
const METHOD_TONES: Record<string, string> = {
    GET: SUCCESS,
    POST: WARNING,
    PUT: WARNING,
    PATCH: WARNING,
    DELETE: DESTRUCTIVE,
};

let reveals = 0;
const revealListeners = new Set<() => void>();

function subscribeToLinks(onChange: () => void): () => void {
    window.addEventListener('hashchange', onChange);
    revealListeners.add(onChange);

    return () => {
        window.removeEventListener('hashchange', onChange);
        revealListeners.delete(onChange);
    };
}

/**
 * Points the page at an endpoint, as a link to it would. When the hash already points at it, the browser neither
 * scrolls nor says so, so the endpoint is scrolled to and told to open here.
 */
export function revealEndpoint(anchor: string): void {
    if (window.location.hash !== `#${anchor}`) {
        window.location.hash = anchor;

        return;
    }

    reveals += 1;
    revealListeners.forEach((listener) => listener());
    document.getElementById(anchor)?.scrollIntoView();
}

/**
 * Whether an endpoint is unfolded: as the reader left it, until a link points at it anew and opens it. One a link
 * opened stays open when the hash moves on: folding it would pull up whatever the new link scrolled to.
 */
export function useEndpointOpen(
    anchor: string,
): [boolean, (open: boolean) => void] {
    const link = useSyncExternalStore(
        subscribeToLinks,
        () => (window.location.hash === `#${anchor}` ? reveals : null),
        () => null,
    );
    const [toggled, setToggled] = useState<boolean | null>(null);
    const [lastLink, setLastLink] = useState(link);

    if (link !== lastLink) {
        setLastLink(link);

        if (link !== null) {
            setToggled(null);
        } else if (toggled === null && lastLink !== null) {
            setToggled(true);
        }
    }

    return [toggled ?? link !== null, setToggled];
}

export function endpointAnchor(endpoint: { id: string }): string {
    return `endpoint-${endpoint.id.replace(/[^A-Za-z0-9_-]+/g, '-')}`;
}

/**
 * A path with its parameters muted, so the fixed part reads first: `/users/{handle}/commits`.
 */
export function EndpointPath({ path }: { path: string }) {
    return (
        <span className="font-mono text-[13px] [overflow-wrap:anywhere]">
            {path.split(/(\{[^}]+\})/).map((part, index) => (
                <Fragment key={index}>
                    {part.startsWith('{') ? (
                        <span className="text-muted-foreground">{part}</span>
                    ) : (
                        part
                    )}
                </Fragment>
            ))}
        </span>
    );
}

export function MethodChip({ method }: { method: string }) {
    return (
        <span
            className={cn(
                'min-w-[3.75rem] shrink-0 rounded-sm px-1.5 text-center font-mono text-[12px] leading-5 font-medium',
                METHOD_TONES[method] ?? 'bg-muted',
            )}
        >
            {method}
        </span>
    );
}

export function SignatureChip({
    signature,
}: {
    signature: ApiDocsEndpoint['signature'];
}) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center gap-1 rounded-sm border px-1.5 font-mono text-[12px] leading-5',
                signature === 'required' ? WARNING : 'text-muted-foreground',
            )}
        >
            {signature === 'required' && (
                <Lock aria-hidden className="size-3" />
            )}
            {signature === 'required' ? 'signed' : 'public'}
        </span>
    );
}

function Part({
    title,
    meta,
    actions,
    children,
}: {
    title: string;
    meta?: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="space-y-2" data-part={actions ? '' : undefined}>
            <div className="flex items-center gap-2">
                <h3 className="font-mono text-[13px] text-muted-foreground">
                    {title}
                    {meta && <span className="ml-2 text-[12px]">{meta}</span>}
                </h3>
                {actions && <div className="ml-auto">{actions}</div>}
            </div>
            {children}
        </div>
    );
}

type View = 'fields' | 'schema';

type Views = Record<'body' | 'responses' | 'errors', View>;

/**
 * Every part of an endpoint as fields, which is how each one first reads.
 */
export const FIELDS_EVERYWHERE: Views = {
    body: 'fields',
    responses: 'fields',
    errors: 'fields',
};

/**
 * The views with one part read another way: each part has its own toggle, picked for it alone.
 */
export function pickView(views: Views, part: keyof Views, view: View): Views {
    return { ...views, [part]: view };
}

/**
 * Whether a part reads as JSON schema, which only then makes the endpoint ask for its schemas.
 */
export function wantsSchemas(views: Views): boolean {
    return Object.values(views).includes('schema');
}

/**
 * Fields or JSON schema, for what one part of an endpoint takes or answers: a bordered segmented pair, the picked one
 * on the Blue Wash like the theme switch, its 12px label in the pressed blue, which reads at 4.5:1 on the wash where
 * Commit Blue does not.
 */
function ViewToggle({
    view,
    onChange,
}: {
    view: View;
    onChange: (view: View) => void;
}) {
    return (
        <div
            role="group"
            aria-label="Show as"
            className="flex rounded-md border p-0.5 font-mono text-[12px]"
        >
            {(['fields', 'schema'] as const).map((option) => (
                <button
                    key={option}
                    type="button"
                    aria-pressed={view === option}
                    onClick={() => onChange(option)}
                    className={cn(
                        'scroll-mt-32 rounded-sm px-2 leading-5 text-muted-foreground transition-colors hover:text-foreground lg:scroll-mt-20',
                        view === option &&
                            'bg-primary-soft text-primary-hover hover:text-primary-hover',
                    )}
                >
                    {option}
                </button>
            ))}
        </div>
    );
}

/**
 * The fields, kept in place until their schema arrives so nothing moves, and a word over them only once it takes a
 * while.
 */
function FieldsWhileLoading({ fields }: { fields: ApiDocsField[] }) {
    const isLate = useIsLate(true);

    return (
        <div className="relative">
            <div
                className={cn(
                    'motion-safe:transition-opacity',
                    isLate && 'opacity-50',
                )}
            >
                <FieldTable fields={fields} />
            </div>
            <p
                role="status"
                className="pointer-events-none absolute inset-0 flex items-center justify-center"
            >
                {isLate && (
                    <span className="rounded-sm border bg-card px-2 font-mono text-[13px] leading-6 text-muted-foreground">
                        Loading the schema…
                    </span>
                )}
            </p>
        </div>
    );
}

/**
 * Fields as a table, or the JSON schema they come from, loaded when the reader asks for it.
 */
export function SchemaOrFields({
    fields,
    view,
    schemas,
    pick,
    onRetry,
}: {
    fields: ApiDocsField[];
    view: 'fields' | 'schema';
    schemas: SchemasState;
    pick: (schemas: ApiDocsSchemas) => ApiDocsSchema | null | undefined;
    onRetry: (from: Element) => void;
}) {
    if (view === 'fields') {
        return <FieldTable fields={fields} />;
    }

    if (schemas === 'failed') {
        return (
            <div className="relative">
                <div className="opacity-50">
                    <FieldTable fields={fields} />
                </div>
                <p className="pointer-events-none absolute inset-0 flex items-start justify-center p-2">
                    <span className="pointer-events-auto flex max-w-full min-w-0 items-baseline gap-x-2 rounded-sm border bg-card px-2 font-mono text-[13px] leading-6 whitespace-nowrap text-muted-foreground">
                        <span className="min-w-0 truncate">
                            fatal: no schema
                        </span>
                        <button
                            type="button"
                            onClick={(event) => onRetry(event.currentTarget)}
                            className="inline-flex min-h-6 shrink-0 items-center font-sans text-sm text-primary hover:underline"
                        >
                            Try again
                        </button>
                    </span>
                </p>
            </div>
        );
    }

    if (schemas === undefined || schemas === 'loading') {
        return <FieldsWhileLoading fields={fields} />;
    }

    const schema = pick(schemas);

    return schema ? (
        <div className="max-h-[32rem] overflow-y-auto overscroll-contain rounded-md">
            <CodeBlock html={schema.html} language="json" filename={null} />
        </div>
    ) : (
        <FieldTable fields={fields} />
    );
}

function Answers({
    responses,
    view,
    schemas,
    onRetry,
}: {
    responses: ApiDocsResponse[];
    view: 'fields' | 'schema';
    schemas: SchemasState;
    onRetry: (from: Element) => void;
}) {
    return (
        <ul className="divide-y rounded-lg border">
            {responses.map((response) => (
                <li key={response.status} className="space-y-2 px-3 py-2.5">
                    <p className="flex items-baseline gap-3 text-sm">
                        <span className="font-mono text-[13px] font-medium tabular-nums">
                            {response.status}
                        </span>
                        <span className="text-muted-foreground">
                            <SpecText text={response.description} />
                        </span>
                        {isSharedError(response) && (
                            <Link
                                href={ApiDocsController.show('errors')}
                                prefetch
                                className="ml-auto shrink-0 text-primary"
                            >
                                see Errors
                            </Link>
                        )}
                    </p>
                    {response.fields.length > 0 && (
                        <SchemaOrFields
                            fields={response.fields}
                            view={view}
                            schemas={schemas}
                            pick={(loaded) => loaded.responses[response.status]}
                            onRetry={onRetry}
                        />
                    )}
                </li>
            ))}
        </ul>
    );
}

/**
 * Says once, to a screen reader, that an endpoint's schema failed to load: each of its fields tables only shows it.
 */
export function SchemaFailureAlert({
    endpoint,
    schemas,
}: {
    endpoint: Pick<ApiDocsEndpoint, 'method' | 'path'>;
    schemas: SchemasState;
}) {
    if (schemas !== 'failed') {
        return null;
    }

    return (
        <p role="alert" className="sr-only">
            {`The schema of ${endpoint.method} ${endpoint.path.replace(/[{}]/g, '')} could not be loaded.`}
        </p>
    );
}

/**
 * Asks for a schema again, and hands keyboard focus to what stays on screen: the button pressed goes away with the
 * failure, which would send focus back to the top of the page. A schema that loads can be much shorter than the
 * fields it replaces, so once it is drawn, the focused toggle is brought back in sight if it left it, unless the
 * reader scrolled, or moved focus, meanwhile: what they did then wins.
 */
export async function retryKeepingFocus(
    retry: () => Promise<void>,
    target: HTMLElement | null,
): Promise<void> {
    if (target === null) {
        return retry();
    }

    let isReleased = false;
    let isLoaded = false;
    const release = () => {
        isReleased = true;
    };
    // Any scroll while the schema loads is the reader's: nothing moves on the page meanwhile. Once it is drawn, the page
    // may scroll by itself as it gets shorter, so only the reader's own input still counts until that frame.
    const inputs = ['wheel', 'touchmove', 'keydown'] as const;
    const stopListening = () =>
        inputs.forEach((input) => window.removeEventListener(input, release));
    [...inputs, 'scroll' as const].forEach((input) =>
        window.addEventListener(input, release, { passive: true }),
    );

    try {
        const loading = retry();
        target.focus({ preventScroll: true });
        await loading;
        isLoaded = true;
    } finally {
        window.removeEventListener('scroll', release);

        if (!isLoaded) {
            stopListening();
        }
    }

    if (isReleased || document.activeElement !== target) {
        stopListening();

        return;
    }

    requestAnimationFrame(() => {
        stopListening();

        if (!isReleased) {
            target.scrollIntoView({ block: 'nearest' });
        }
    });
}

/**
 * The toggle of the part a Try again was pressed in, which stays right where the reader is, unlike another part's.
 */
export function retryTarget(from: Element): HTMLElement | null {
    return (
        from
            .closest('[data-part]')
            ?.querySelector<HTMLElement>('button[aria-pressed="true"]') ?? null
    );
}

/**
 * Whether an endpoint shows any fields its schemas would replace: one without has nothing to ask them for.
 */
export function hasSchemaTables(
    endpoint: Pick<ApiDocsEndpoint, 'body' | 'responses'>,
): boolean {
    return (
        (endpoint.body?.fields.length ?? 0) > 0 ||
        endpoint.responses.some((response) => response.fields.length > 0)
    );
}

/**
 * What an endpoint takes, its path and query parameters apart, and what it answers, its errors apart. Its body, its
 * responses and its errors each read as fields or as JSON schema, as the reader picks for that part alone.
 */
export function EndpointDetails({
    endpoint,
    section,
}: {
    endpoint: ApiDocsEndpoint;
    section: string;
}) {
    const [views, setViews] = useState(FIELDS_EVERYWHERE);
    const toggle = (part: keyof Views) => (
        <ViewToggle
            view={views[part]}
            onChange={(view) => setViews(pickView(views, part, view))}
        />
    );
    const schemasUrl =
        wantsSchemas(views) && hasSchemaTables(endpoint)
            ? ApiDocsSchemaController.show({ section, endpoint: endpoint.id })
                  .url
            : null;
    const schemas = useSchemas(schemasUrl);
    const retry = (from: Element) => {
        if (schemasUrl !== null) {
            void retryKeepingFocus(
                () => loadSchemas(schemasUrl),
                retryTarget(from),
            );
        }
    };
    const path = endpoint.parameters.filter(
        (parameter) => parameter.in === 'path',
    );
    const query = endpoint.parameters.filter(
        (parameter) => parameter.in === 'query',
    );
    const successes = endpoint.responses.filter((response) =>
        response.status.startsWith('2'),
    );
    const errors = endpoint.responses.filter(
        (response) => !response.status.startsWith('2'),
    );

    return (
        <div className="min-w-0 space-y-5 px-4 py-4">
            <SchemaFailureAlert endpoint={endpoint} schemas={schemas} />
            <div className="space-y-2 text-[15px] leading-relaxed text-pretty">
                <p>
                    <SpecText text={endpoint.summary} />
                </p>
                {endpoint.description && (
                    <p className="whitespace-pre-line text-muted-foreground">
                        <SpecText text={endpoint.description} />
                    </p>
                )}
            </div>

            {path.length > 0 && (
                <Part title="path parameters">
                    <FieldTable fields={path} />
                </Part>
            )}

            {query.length > 0 && (
                <Part title="query parameters">
                    <FieldTable fields={query} />
                </Part>
            )}

            {endpoint.body && endpoint.body.fields.length > 0 && (
                <Part
                    title="body"
                    meta={endpoint.body.mediaType}
                    actions={toggle('body')}
                >
                    <SchemaOrFields
                        fields={endpoint.body.fields}
                        view={views.body}
                        schemas={schemas}
                        pick={(loaded) => loaded.body}
                        onRetry={retry}
                    />
                </Part>
            )}

            {successes.length > 0 && (
                <Part
                    title="responses"
                    actions={
                        successes.some((response) => response.fields.length > 0)
                            ? toggle('responses')
                            : undefined
                    }
                >
                    <Answers
                        responses={successes}
                        view={views.responses}
                        schemas={schemas}
                        onRetry={retry}
                    />
                </Part>
            )}

            {errors.length > 0 && (
                <Part
                    title="errors"
                    actions={
                        errors.some((response) => response.fields.length > 0)
                            ? toggle('errors')
                            : undefined
                    }
                >
                    <Answers
                        responses={errors}
                        view={views.errors}
                        schemas={schemas}
                        onRetry={retry}
                    />
                </Part>
            )}
        </div>
    );
}

/**
 * An endpoint of the reference: folded to its method, path and summary, and unfolded to what it takes and what it
 * answers. The one a link points at opens by itself.
 */
export function Endpoint({
    endpoint,
    section,
}: {
    endpoint: ApiDocsEndpoint;
    section: string;
}) {
    const anchor = endpointAnchor(endpoint);
    const [isOpen, setIsOpen] = useEndpointOpen(anchor);

    return (
        <section
            id={anchor}
            aria-label={`${endpoint.method} ${endpoint.path}`}
            className="min-w-0 scroll-mt-32 rounded-lg border bg-card lg:scroll-mt-20"
        >
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                aria-expanded={isOpen}
                className="flex min-h-12 w-full flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 text-left hover:bg-accent/50"
            >
                <MethodChip method={endpoint.method} />
                <EndpointPath path={endpoint.path} />
                <span className="min-w-0 flex-[1_1_12rem] truncate text-sm text-muted-foreground">
                    {endpoint.summary}
                </span>
                <SignatureChip signature={endpoint.signature} />
                <ChevronRight
                    aria-hidden
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground transition-transform motion-reduce:transition-none',
                        isOpen && 'rotate-90',
                    )}
                />
            </button>

            {isOpen && (
                <div className="grid border-t lg:grid-cols-2">
                    <EndpointDetails endpoint={endpoint} section={section} />
                    {endpoint.examples.length > 0 && (
                        <div className="min-w-0 border-t bg-background/40 px-4 py-4 lg:border-t-0 lg:border-l">
                            <div className="lg:sticky lg:top-20">
                                <ExampleTabs
                                    examples={endpoint.examples}
                                    holdHeight={false}
                                />
                            </div>
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}
