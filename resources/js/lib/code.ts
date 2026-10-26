import type { BodySegment } from '@/types';

// Keep in sync with app/Services/CodeSpans.php: a fenced code block, from a line starting with ``` or more (and maybe
// a language) to a line that is only ``` or more, or inline code between two backticks on one line.
const committedCode = String.raw`(?<![^\n])\`{3,}[^\`\r\n]*\r?\n(?:[\s\S]*?\r?\n)?\`{3,}[ \t]*(?![^\r\n])|\`[^\`\r\n]+\``;
export const codePattern = new RegExp(`(${committedCode})`);
// While typing, a block or inline code that isn't closed yet also runs to the end of the text.
const typedCodePattern = new RegExp(
    `(${committedCode}|${String.raw`(?<![^\n])\`\`\`[\s\S]*$|\`[^\`\r\n]*$`})`,
);

const isBlock = (part: string | undefined): boolean =>
    part?.startsWith('```') ?? false;

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
 * Applies the callback to prose, leaving committed code as written, just like the server.
 */
export function mapProse(
    text: string,
    callback: (prose: string) => string,
): string {
    return text
        .split(codePattern)
        .map((part, index) => (index % 2 === 0 ? callback(part) : part))
        .join('');
}

/**
 * The text with its committed code blanked out to spaces of the same length, so what is found in the prose keeps its
 * place in the text.
 */
export function withoutCode(text: string): string {
    return text
        .split(codePattern)
        .map((part, index) =>
            index % 2 === 0 ? part : ' '.repeat(part.length),
        )
        .join('');
}

/**
 * Applies the callback to the prose being typed only, leaving the code as written.
 */
export function mapTypedProse(
    text: string,
    callback: (prose: string) => string,
): string {
    return text
        .split(typedCodePattern)
        .map((part, index) => (index % 2 === 0 ? callback(part) : part))
        .join('');
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
                    : { type: 'code', code: part.slice(1, -1) },
            );

            return;
        }

        let prose = part;

        if (isBlock(parts[index - 1])) {
            prose = prose.replace(/^\r?\n/, '');
        }

        if (isBlock(parts[index + 1])) {
            prose = prose.replace(/\r?\n$/, '');
        }

        if (prose !== '') {
            segments.push({ type: 'text', text: prose });
        }
    });

    return segments;
}

export function hasCodeBlock(segments: BodySegment[]): boolean {
    return segments.some((segment) => segment.type === 'block');
}

/**
 * Groups quoted prose lines without interpreting markers inside code or changing the stored segments.
 */
export function bodyBlocks(
    segments: BodySegment[],
): { quoted: boolean; segments: BodySegment[] }[] {
    const blocks: { quoted: boolean; segments: BodySegment[] }[] = [];
    let atLineStart = true;
    let quoted = false;

    for (const segment of segments.flatMap((segment): BodySegment[] =>
        segment.type === 'text'
            ? segment.text
                  .split(/(?<=\n)/)
                  .map((text) => ({ type: 'text', text }))
            : [segment],
    )) {
        if (atLineStart || segment.type === 'block') {
            quoted = segment.type === 'text' && segment.text.startsWith('>');
        }

        const content =
            atLineStart && quoted && segment.type === 'text'
                ? { ...segment, text: segment.text.replace(/^> ?/, '') }
                : segment;
        const previous = blocks.at(-1);

        if (previous?.quoted === quoted) {
            previous.segments.push(content);
        } else {
            const last = previous?.segments.at(-1);

            if (last?.type === 'text') {
                last.text = last.text.replace(/\r?\n$/, '');
            }

            blocks.push({ quoted, segments: [content] });
        }

        atLineStart =
            segment.type === 'block' ||
            (segment.type === 'text' && segment.text.endsWith('\n'));
    }

    return blocks;
}

/**
 * The prose being typed, without the code.
 */
export function typedProse(text: string): string[] {
    return text.split(typedCodePattern).filter((_, index) => index % 2 === 0);
}
