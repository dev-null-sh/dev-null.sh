import { typedProse, withoutCode } from '@/lib/code';
import { colorsIn } from '@/lib/colors';

export function issueReferenceBeforeCaret(text: string, caret: number) {
    const prose = typedProse(text.slice(0, caret)).at(-1) ?? '';
    const match = prose.match(/(?:^|[\s([{])#([1-9][0-9]{0,9})$/u);

    if (!match) {
        return null;
    }

    if (/^[\p{L}\p{N}_/#-]/u.test(text.slice(caret))) {
        return null;
    }

    const start = caret - match[1].length - 1;

    // A color like "#333333" is all digits too, but it is never an issue.
    if (
        colorsIn(withoutCode(text), true).some((color) => color.index === start)
    ) {
        return null;
    }

    return {
        start,
        query: match[1],
    };
}
