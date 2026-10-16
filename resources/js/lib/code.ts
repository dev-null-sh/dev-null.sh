// Keep in sync with app/Services/CodeSpans.php: a fenced code block, from a line starting with ``` or more (and maybe
// a language) to a line that is only ``` or more, or inline code between two backticks on one line. While typing, a
// block or inline code that isn't closed yet also runs to the end of the text.
const codePattern = new RegExp(
    String.raw`((?<![^\n])\`{3,}[^\`\r\n]*\r?\n(?:[\s\S]*?\r?\n)?\`{3,}[ \t]*(?![^\r\n])|\`[^\`\r\n]+\`` +
        String.raw`|(?<![^\n])\`\`\`[\s\S]*$|\`[^\`\r\n]*$)`,
);

/**
 * Applies the callback to the prose being typed only, leaving the code as written.
 */
export function mapTypedProse(
    text: string,
    callback: (prose: string) => string,
): string {
    return text
        .split(codePattern)
        .map((part, index) => (index % 2 === 0 ? callback(part) : part))
        .join('');
}

/**
 * The prose being typed, without the code.
 */
export function typedProse(text: string): string[] {
    return text.split(codePattern).filter((_, index) => index % 2 === 0);
}
