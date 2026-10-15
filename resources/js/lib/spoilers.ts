import { mapTypedProse } from '@/lib/code';
import type { Commit } from '@/types';

// Keep in sync with app/Services/Spoilers.php: text between "||" and "||" that neither starts nor ends with whitespace.
const spoilerPattern = /\|\|(?=\S)([\s\S]+?)(?<=\S)\|\|/gu;

/**
 * The commit as one line of plain text that gives nothing away: its content warning when it has one, otherwise its
 * body with every spoiler hidden.
 */
export function commitExcerpt(
    commit: Pick<Commit, 'body' | 'content_warning'>,
): string {
    if (commit.content_warning !== null) {
        return `CW: ${commit.content_warning}`;
    }

    return mapTypedProse(commit.body, (prose) =>
        prose.replace(spoilerPattern, '[spoiler]'),
    );
}
