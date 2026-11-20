import { withoutTypedCode } from '@/lib/code';
import { isHexColor, isTypingExplicitColor } from '@/lib/colors';
import { hrefFor, urlPattern, withoutTrailingPunctuation } from '@/lib/urls';

export const hashtagPattern =
    /(?<![\p{L}\p{M}\p{N}_&/#>-])#\p{L}[\p{L}\p{M}\p{N}_]*/gu;

type AmbiguousColor = {
    index: number;
    hex: string;
};

export function ambiguousColorInDraft(
    text: string,
): AmbiguousColor | undefined {
    const prose = withoutTypedCode(text).replace(urlPattern, (candidate) => {
        const link = withoutTrailingPunctuation(candidate);

        return hrefFor(link) === null
            ? candidate
            : ' '.repeat(link.length) + candidate.slice(link.length);
    });
    const match = [...prose.matchAll(hashtagPattern)].findLast(
        ({ 0: hex, index }) =>
            isHexColor(hex) && !isTypingExplicitColor(prose.slice(0, index)),
    );

    return match && { index: match.index, hex: match[0] };
}
