import { withoutTypedCode } from '@/lib/code';
import { isHexColor, isTypingExplicitColor } from '@/lib/colors';
import { hrefFor, urlPattern, withoutTrailingPunctuation } from '@/lib/urls';

// Keep the handle in sync with app/Services/Hashtags.php. It may start with numbers, as in "3dprinting", but then needs
// a letter so "#123" stays an issue reference, and must not be hex so "#3d3d3d" stays a color.
const handle = String.raw`(?![0-9](?:[0-9a-fA-F]{7}|[0-9a-fA-F]{5}|[0-9a-fA-F]{2,3})_*(?![\p{L}\p{M}\p{N}_]))\p{N}*\p{L}[\p{L}\p{M}\p{N}_]*`;
const handlePattern = new RegExp(
    String.raw`^(?=[\p{L}\p{M}\p{N}_]{1,60}$)${handle}$`,
    'u',
);

export const hashtagPattern = new RegExp(
    String.raw`(?<![\p{L}\p{M}\p{N}_&/#>-])#${handle}`,
    'gu',
);

export const typedHashtagPattern = new RegExp(
    String.raw`(?:^|[\s([{])#(${handle}|)$`,
    'u',
);

export function handleFor(name: string): string {
    return name.replace(/[^\p{L}\p{M}\p{N}_]+/gu, '').toLowerCase();
}

export function isHashtagHandle(text: string): boolean {
    return handlePattern.test(text);
}

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
