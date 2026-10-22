import { typedProse } from '@/lib/code';

export function issueReferenceBeforeCaret(text: string, caret: number) {
    const prose = typedProse(text.slice(0, caret)).at(-1) ?? '';
    const match = [
        ...prose.matchAll(
            /(?:^|[\s([{])#([1-9][0-9]{0,9})(?=$|[\s.,!?;:)\]}])/gu,
        ),
    ].at(-1);

    if (!match) {
        return null;
    }

    const start =
        caret - prose.length + match.index + match[0].lastIndexOf('#');
    const complete = start + match[1].length + 1 < caret;

    if (!complete && /^[\p{L}\p{N}_/#-]/u.test(text.slice(caret))) {
        return null;
    }

    return {
        start,
        query: match[1],
        complete,
    };
}
