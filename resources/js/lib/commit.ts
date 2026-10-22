import { bodySegments, mapProse } from '@/lib/code';
import { emojify } from '@/lib/emoji';
import { hrefFor, urlPattern, withoutTrailingPunctuation } from '@/lib/urls';
import { characterCount } from '@/lib/utils';

// Keep in sync with App\Models\Commit.
export const minBodyLength = 1;
export const maxBodyLength = 4000;
// Keep in sync with App\Rules\CommitBodyLength.
export const maxRawBodyLength = 40000;
export const previewLength = 280;
export const previewLines = 8;

/**
 * The body as it will be saved: trimmed, with runs of blank lines in the prose collapsed into one. Keep in sync with
 * App\Services\CommitBodies::normalize().
 */
function normalizeBody(body: string): string {
    return mapProse(body.trim().replace(/\r\n?/g, '\n'), (prose) =>
        prose.replace(/(?:[ \t]*\n){3,}/g, '\n\n'),
    );
}

/**
 * Whether a draft is the body a commit was saved with, once normalized and with its shortcodes turned into emoji.
 */
export function isSavedAs(draft: string, saved: string): boolean {
    return emojify(normalizeBody(draft)) === saved;
}

/**
 * Each prose link counts as at most 23 characters. Keep in sync with App\Services\Urls::characterCount().
 */
export function bodyCharacterCount(body: string): number {
    const normalized = normalizeBody(body);
    let length = characterCount(normalized);

    for (const segment of bodySegments(normalized)) {
        if (segment.type !== 'text') continue;

        for (const match of segment.text.matchAll(urlPattern)) {
            const link = withoutTrailingPunctuation(match[0]);

            if (hrefFor(link) !== null) {
                length -= Math.max(0, characterCount(link) - 23);
            }
        }
    }

    return length;
}

export function isFoldedInLists(body: string): boolean {
    const normalized = normalizeBody(body);

    return (
        characterCount(normalized) > previewLength ||
        normalized.split('\n').length > previewLines
    );
}
