export type MarkdownFormat =
    | 'bold'
    | 'italic'
    | 'code'
    | 'link'
    | 'heading'
    | 'bulleted'
    | 'numbered'
    | 'quote';

/**
 * Replace `from` to `to` with `insert`, then select `selectionStart` to `selectionEnd`.
 */
export type MarkdownEdit = {
    from: number;
    to: number;
    insert: string;
    selectionStart: number;
    selectionEnd: number;
};

const WRAPS: Partial<Record<MarkdownFormat, [string, string]>> = {
    bold: ['**', '**'],
    italic: ['_', '_'],
    code: ['`', '`'],
    link: ['[', '](https://)'],
};

const PREFIXES: Partial<Record<MarkdownFormat, (line: number) => string>> = {
    heading: () => '## ',
    bulleted: () => '- ',
    numbered: (line) => `${line + 1}. `,
    quote: () => '> ',
};

/**
 * What a toolbar button does to the selection: wraps it inline, or prefixes every line it touches. A new link selects
 * its URL placeholder, so typing replaces it.
 */
export function formatMarkdown(
    value: string,
    start: number,
    end: number,
    format: MarkdownFormat,
): MarkdownEdit {
    const wrap = WRAPS[format];

    if (wrap) {
        const [before, after] = wrap;
        const selected = value.slice(start, end);
        const insert = `${before}${selected}${after}`;

        if (format === 'link') {
            const url = start + before.length + selected.length + 2;

            return {
                from: start,
                to: end,
                insert,
                selectionStart: url,
                selectionEnd: url + 'https://'.length,
            };
        }

        return {
            from: start,
            to: end,
            insert,
            selectionStart: start + before.length,
            selectionEnd: end + before.length,
        };
    }

    const prefix = PREFIXES[format] ?? (() => '');
    const from = value.lastIndexOf('\n', start - 1) + 1;
    const lineEnd = value.indexOf('\n', end);
    const to = lineEnd === -1 ? value.length : lineEnd;
    const insert = value
        .slice(from, to)
        .split('\n')
        .map((line, index) => `${prefix(index)}${line}`)
        .join('\n');

    return {
        from,
        to,
        insert,
        selectionStart: from + insert.length,
        selectionEnd: from + insert.length,
    };
}
