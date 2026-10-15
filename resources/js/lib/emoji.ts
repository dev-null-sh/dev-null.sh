const shortcodeEmoji = {
    heart: '❤️',
    like: '👍',
    dislike: '👎',
    laugh: '😂',
    smile: '😄',
    sad: '😢',
    angry: '😠',
    fire: '🔥',
    rocket: '🚀',
    clap: '👏',
    eyes: '👀',
    party: '🎉',
} as const;

const shortcodeNames = Object.keys(shortcodeEmoji).sort(
    (left, right) => right.length - left.length,
);

const shortcodePattern = new RegExp(
    `:(${shortcodeNames.join('|')})(?![A-Za-z0-9_])`,
    'g',
);

export function emojify(text: string): string {
    return text.replace(
        shortcodePattern,
        (_, name: string) =>
            shortcodeEmoji[name as keyof typeof shortcodeEmoji],
    );
}

export function emojifyInput(
    text: string,
    caret: number,
): { text: string; caret: number } {
    return {
        text: emojify(text),
        caret: emojify(text.slice(0, caret)).length,
    };
}
