/**
 * Mermaid stays out of the normal feed bundle. The renderer is loaded only for a diagram
 * approaching the viewport, and its SVG is displayed as an inert image, never as DOM HTML.
 */
import { mermaidTheme } from '@/lib/mermaid-theme';
import type { DiagramTheme } from '@/lib/mermaid-theme';

export const MAX_DIAGRAM_LENGTH = 6000;

// Source is untrusted. Mermaid can load images while building its temporary DOM,
// before the final SVG is isolated in an <img>. Fall back to source for media.
// Mermaid's YAML node-metadata parser decodes backslash-escaped key names.
// Reject escapes after @{, including an escaped img key after a quoted `}`.
const EMBEDDED_MEDIA =
    /\bimg['"]?\s*:|<\s*(?:img|image|picture|source|svg)\b|!\[[^\]\r\n]*\]\s*\(|\burl\s*\(|@\{[\s\S]*\\/i;

type Mermaid = (typeof import('mermaid'))['default'];

let engine: Promise<Mermaid> | null = null;
let renderQueue: Promise<void> = Promise.resolve();
let nextId = 0;

function loadMermaid(): Promise<Mermaid> {
    engine ??= import('mermaid').then(({ default: mermaid }) => mermaid);

    return engine;
}

/**
 * Mermaid writes temporary DOM nodes during rendering. Its shared configuration is global,
 * so serialize renders to avoid cross-diagram races and theme bleeding.
 */
export function renderMermaid(
    source: string,
    theme: DiagramTheme,
): Promise<string> {
    if (!source.trim() || source.length > MAX_DIAGRAM_LENGTH) {
        return Promise.reject(
            new Error('Diagram source is empty or too long.'),
        );
    }

    if (EMBEDDED_MEDIA.test(source)) {
        return Promise.reject(new Error('Embedded images are not supported.'));
    }

    const task = renderQueue.then(async (): Promise<string> => {
        const mermaid = await loadMermaid();

        mermaid.initialize({
            startOnLoad: false,
            suppressErrorRendering: true,
            securityLevel: 'strict',
            maxTextSize: MAX_DIAGRAM_LENGTH,
            maxEdges: 80,
            theme: 'base',
            flowchart: { htmlLabels: false },
            themeVariables: mermaidTheme(theme),
        });

        if (!(await mermaid.parse(source, { suppressErrors: true }))) {
            throw new Error('Invalid Mermaid syntax.');
        }

        const rendered = await mermaid.render(
            `devnull-diagram-${++nextId}`,
            source,
        );

        return rendered.svg;
    });

    renderQueue = task.then(
        () => undefined,
        () => undefined,
    );

    return task;
}
