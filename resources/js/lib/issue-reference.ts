import { typedProse } from '@/lib/code';

export function issueReferenceBeforeCaret(text: string, caret: number) {
    const prose = typedProse(text.slice(0, caret)).at(-1) ?? '';
    const match = prose.match(/(?:^|[\s([{])#([1-9][0-9]{0,9})$/u);

    if (!match) {
        return null;
    }

    if (/^[\p{L}\p{N}_/#-]/u.test(text.slice(caret))) {
        return null;
    }

    return {
        start: caret - match[1].length - 1,
        query: match[1],
    };
}
