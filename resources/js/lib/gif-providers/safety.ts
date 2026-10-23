const blockedTerms = [
    'porn',
    'pornography',
    'nsfw',
    'xxx',
    'hentai',
    'nudity',
    'nude',
    'naked',
    'erotic',
    'sex',
    'gore',
    'gory',
    'cocaine',
    'heroin',
    'methamphetamine',
];

const blockedPattern = new RegExp(`\\b(?:${blockedTerms.join('|')})\\b`, 'i');

export function isSafeGiphyRating(rating: string | undefined): boolean {
    return rating?.toLowerCase() === 'g';
}

export function isBlockedGifText(
    ...texts: Array<string | null | undefined>
): boolean {
    return texts.some(
        (text) => typeof text === 'string' && blockedPattern.test(text),
    );
}
