import type { BodySegment } from '@/types';

// Keep in sync with app/Services/CodeSpans.php: a fenced code block, from a line starting with ``` or more (and maybe
// a language) to a line that is only ``` or more, inline code between two backticks on one line, or a details block,
// from a line that is only <details> and one that is only <summary>…</summary> to a line that is only </details>.
const committedCode = String.raw`(?<![^\n])\`{3,}[^\`\r\n]*\r?\n(?:[\s\S]*?\r?\n)?\`{3,}[ \t]*(?![^\r\n])|\`[^\`\r\n]+\``;
const committedDetails = String.raw`(?<![^\n])<details>[ \t]*\r?\n[ \t]*<summary>[^\r\n]*<\/summary>[ \t]*\r?\n[\s\S]*?(?<![^\n])<\/details>[ \t]*(?![^\r\n])`;
const codePattern = new RegExp(`(${committedCode}|${committedDetails})`);
// While typing, a block or inline code that isn't closed yet also runs to the end of the text.
const typedCodePattern = new RegExp(
    `(${committedCode}|${committedDetails}|${String.raw`(?<![^\n])\`\`\`[\s\S]*$|\`[^\`\r\n]*$`})`,
);

// The summary and the body of a details block, which the patterns above have already picked out.
const detailsParts = String.raw`^<details>[ \t]*\r?\n[ \t]*<summary>([^\r\n]*)<\/summary>[ \t]*\r?\n([\s\S]*?)(?<![^\n])<\/details>[ \t]*$`;
const detailsPattern = new RegExp(detailsParts);

const isBlock = (part: string | undefined): boolean =>
    part?.startsWith('```') ?? false;

const isDetails = (part: string | undefined): boolean =>
    part?.startsWith('<details>') ?? false;

// Whether a block spans lines on its own: a code block or a details block, each of which the text around it stands
// apart from.
const isStructure = (part: string | undefined): boolean =>
    isBlock(part) || isDetails(part);

function escapeHtml(text: string): string {
    return text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function block(part: string): BodySegment {
    const lines = part.replaceAll('\r\n', '\n').split('\n');
    const language = lines[0].replace(/^`+/, '').trim().split(/[ \t]/)[0];

    return {
        type: 'block',
        language: language === '' ? null : language,
        html: escapeHtml(lines.slice(1, -1).join('\n')),
    };
}

/**
 * A details block, with its body cut up into segments of its own, the way the server does it. The line breaks around
 * the body are dropped, the way the ones around a block are.
 */
function details(part: string): BodySegment {
    const [, summary = '', body = ''] = detailsPattern.exec(part) ?? [];

    return {
        type: 'details',
        summary,
        segments: bodySegments(
            body.replace(/^\r?\n/, '').replace(/\r?\n$/, ''),
        ),
    };
}

/**
 * The summary and the body of a details block. The summary is prose; the body is cut up again, since it holds prose
 * and code of its own.
 */
function detailsProse(part: string): string[] {
    const [, summary = '', body = ''] = detailsPattern.exec(part) ?? [];

    return [summary, ...typedProse(body)];
}

/**
 * Applies the callback to the prose being typed only, leaving the code as written, and reaching into a details block
 * the way the text around it is reached.
 */
export function mapTypedProse(
    text: string,
    callback: (prose: string) => string,
): string {
    return text
        .split(typedCodePattern)
        .map((part, index) => {
            if (index % 2 === 0) {
                return callback(part);
            }

            return isDetails(part) ? mapDetails(part, callback) : part;
        })
        .join('');
}

function mapDetails(part: string, callback: (prose: string) => string): string {
    const [, summary = '', body = ''] = detailsPattern.exec(part) ?? [];
    const eol = part.includes('\r\n') ? '\r\n' : '\n';

    return `<details>${eol}<summary>${callback(summary)}</summary>${eol}${mapTypedProse(body, callback)}</details>`;
}

/**
 * Splits the text into segments the way the server does, with each code block escaped but not highlighted, since
 * only the server highlights code.
 */
export function bodySegments(text: string): BodySegment[] {
    const parts = text.split(codePattern);
    const segments: BodySegment[] = [];

    parts.forEach((part, index) => {
        if (index % 2 === 1) {
            segments.push(
                isBlock(part)
                    ? block(part)
                    : isDetails(part)
                      ? details(part)
                      : { type: 'code', code: part.slice(1, -1) },
            );

            return;
        }

        let prose = part;

        if (isStructure(parts[index - 1])) {
            prose = prose.replace(/^\r?\n/, '');
        }

        if (isStructure(parts[index + 1])) {
            prose = prose.replace(/\r?\n$/, '');
        }

        if (prose !== '') {
            segments.push({ type: 'text', text: prose });
        }
    });

    return segments;
}

/**
 * Whether any of the segments holds a code block, at the top level or inside a details block.
 */
export function hasCodeBlock(segments: BodySegment[]): boolean {
    return segments.some(
        (segment) =>
            segment.type === 'block' ||
            (segment.type === 'details' && hasCodeBlock(segment.segments)),
    );
}

/**
 * The prose being typed, without the code.
 */
export function typedProse(text: string): string[] {
    return text.split(typedCodePattern).flatMap((part, index) => {
        if (index % 2 === 0) {
            return [part];
        }

        return isDetails(part) ? detailsProse(part) : [];
    });
}
