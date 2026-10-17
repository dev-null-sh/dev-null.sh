/**
 * Guesses whether a search reads like someone looking for a person or for an idea. It runs on every keystroke and
 * decides whether an embedding is paid for, so it is a few rules rather than a model: a wrong guess only changes which
 * section comes first, never what can be found.
 */
export type SearchIntent = 'developer' | 'either' | 'topic';

/**
 * GitHub usernames are at most 39 characters, but people rarely type more than this before it is clearly a word.
 */
const longestLikelyHandle = 12;

const handle = /^@/u;
const handleCharacters = /^[a-z0-9-]+$/iu;
const separators = /\s+/u;

export function searchIntent(term: string): SearchIntent {
    const trimmed = term.trim();

    if (handle.test(trimmed)) {
        return 'developer';
    }

    if (looksLikeFullName(trimmed)) {
        return 'either';
    }

    if (separators.test(trimmed)) {
        return 'topic';
    }

    if (looksLikeHandle(trimmed)) {
        return 'developer';
    }

    return 'either';
}

export function searchesCommits(intent: SearchIntent): boolean {
    return intent !== 'developer';
}

/**
 * A lone "@" announces a developer search but has nothing to search for yet.
 */
export function awaitsDeveloperName(term: string): boolean {
    return term.trim() === '@';
}

/**
 * "Daniel Kim" has a space like a topic does, but two or three capitalised words read as a name.
 */
function looksLikeFullName(text: string): boolean {
    const words = text.split(separators);

    return (
        words.length >= 2 &&
        words.length <= 3 &&
        words.every((word) => /^\p{Lu}[\p{L}'-]*$/u.test(word))
    );
}

function looksLikeHandle(word: string): boolean {
    if (!handleCharacters.test(word)) {
        return false;
    }

    return word.length <= longestLikelyHandle && !isDictionaryShaped(word);
}

/**
 * A lowercase word made only of letters, long enough to be a topic ("caching", "sharding"), is as likely a subject as
 * a username. Digits, dashes or capitals tip it towards a handle.
 */
function isDictionaryShaped(word: string): boolean {
    return /^[a-z]{6,}$/u.test(word);
}
