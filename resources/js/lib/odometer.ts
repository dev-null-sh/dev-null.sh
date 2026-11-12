export interface OdometerPlace {
    /** The character shown at this place now. */
    char: string;
    /** The character it replaces, or a space when the count grows into this place. */
    previousChar: string;
    /** Whether this place rolls from the previous character. */
    isRolling: boolean;
}

function isDigit(char: string): boolean {
    return char >= '0' && char <= '9';
}

function rolls(char: string, previousChar: string): boolean {
    if (char === previousChar) {
        return false;
    }

    if (!isDigit(char) && !isDigit(previousChar)) {
        return false;
    }

    if (isDigit(char) && isDigit(previousChar)) {
        return true;
    }

    return isDigit(char) ? previousChar === ' ' : char === ' ';
}

/**
 * The places of the new count, paired with the character each replaces, so the view rolls only what changed. A digit
 * that appears or disappears rolls; a separator or a shortened count's suffix changes in place.
 */
export function odometerPlaces(
    previous: string,
    current: string,
): OdometerPlace[] {
    const length = Math.max(previous.length, current.length);
    const currentChars = current.padStart(length, ' ');
    const previousChars = previous.padStart(length, ' ');

    return Array.from({ length }, (_, index) => {
        const char = currentChars[index];
        const previousChar = previousChars[index];

        return { char, previousChar, isRolling: rolls(char, previousChar) };
    });
}
