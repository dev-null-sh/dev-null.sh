/**
 * The text to insert for what was said, given the text before the caret: the transcript trimmed, with a space in front
 * when it would otherwise stick to the previous word. Empty when nothing was said.
 */
export function dictatedText(before: string, transcript: string): string {
    const text = transcript.trim();

    if (text === '') {
        return '';
    }

    return before === '' || /\s$/u.test(before) ? text : ` ${text}`;
}
