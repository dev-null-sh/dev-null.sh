/**
 * The text to insert for what was said, given the text before and after the caret: the transcript trimmed, with a space
 * on either side where it would otherwise stick to a word. Empty when nothing was said.
 */
export function dictatedText(
    before: string,
    transcript: string,
    after = '',
): string {
    const text = transcript.trim();

    if (text === '') {
        return '';
    }

    const space = (side: string) =>
        side === '' || /\s/u.test(side) ? '' : ' ';

    return space(before.slice(-1)) + text + space(after.slice(0, 1));
}
