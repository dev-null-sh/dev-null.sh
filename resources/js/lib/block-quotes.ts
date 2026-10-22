import type { BodySegment } from '@/types';

export type BodyRun = { isQuote: boolean; segments: BodySegment[] };

/**
 * Groups the segments into runs of prose and block quotes: lines that start with "> ", or are only ">", outside code,
 * without their markers. A quote is a block already, so the line breaks and blank lines around it are dropped, just
 * like the server drops the line breaks around a code block.
 */
export function quoteRuns(segments: BodySegment[]): BodyRun[] {
    let current: BodyRun = { isQuote: false, segments: [] };
    const runs = [current];
    let isLineStart = true;
    // The line breaks before the current line, kept until the line says whether it stays in the same run.
    let breaks = '';

    // Only text this function made is appended to, never a segment it was given.
    const append = (text: string) => {
        const last = current.segments.at(-1);

        if (last?.type === 'text') {
            last.text += text;
        } else if (text !== '') {
            current.segments.push({ type: 'text', text });
        }
    };

    const startLine = (isQuote: boolean) => {
        if (current.isQuote === isQuote) {
            append(breaks);
        } else {
            current = { isQuote, segments: [] };
            runs.push(current);
        }

        breaks = '';
        isLineStart = false;
    };

    for (const segment of segments) {
        if (segment.type !== 'text') {
            // A code block always starts a line, since the server dropped the line break before it.
            if (isLineStart || segment.type === 'block') {
                startLine(false);
            }

            current.segments.push(segment);
            isLineStart = segment.type === 'block';

            continue;
        }

        segment.text.split('\n').forEach((line, index) => {
            if (index > 0) {
                breaks += '\n';
                isLineStart = true;
            }

            if (!isLineStart) {
                append(line);
            } else if (line !== '') {
                // A ">" followed by anything else, like ">_<", is prose.
                const isQuote = /^>(?: |$)/.test(line);

                startLine(isQuote);
                append(isQuote ? line.slice(2) : line);
            }
        });
    }

    if (!current.isQuote) {
        append(breaks);
    }

    // The first run is empty prose when the body starts with a quote.
    return runs.filter((run) => run.isQuote || run.segments.length > 0);
}
