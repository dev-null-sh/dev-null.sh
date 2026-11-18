import { withoutTypedCode } from '@/lib/code';
import { isHexColor, isTypingExplicitColor } from '@/lib/colors';
import { hrefFor, urlPattern, withoutTrailingPunctuation } from '@/lib/urls';

// Keep the handle in sync with app/Services/Hashtags.php.
const handle = String.raw`\p{L}[\p{L}\p{M}\p{N}_]*`;
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
