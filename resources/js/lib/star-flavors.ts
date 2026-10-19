export type StarFlavor = string;

/**
 * The flavors a star can have, in the picker's order, with what each one says.
 */
export const starFlavors: {
    flavor: StarFlavor;
    emoji: string;
    meaning: string;
}[] = [
    { flavor: 'star', emoji: '⭐', meaning: 'Star it' },
    { flavor: 'ship', emoji: '🚀', meaning: 'Ship it' },
    { flavor: 'fire', emoji: '🔥', meaning: 'This is fire' },
    { flavor: 'brain', emoji: '🧠', meaning: 'Big brain' },
    { flavor: 'party', emoji: '🎉', meaning: 'Congrats' },
    { flavor: 'eyes', emoji: '👀', meaning: 'Watching this' },
];

export const flavorEmoji = (flavor: StarFlavor): string =>
    starFlavors.find((candidate) => candidate.flavor === flavor)?.emoji ??
    flavor;
