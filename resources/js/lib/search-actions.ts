import type { IssueKind } from '@/lib/issue-commands';

/**
 * Things search can do instead of find, so "commit", "bug" or "go live" are a few keystrokes from anywhere.
 */
export type SearchAction = 'commit' | IssueKind | 'go-live' | 'end-stream';

/**
 * An action is offered once the search is the start of one of its words. Ending a stream only answers to being asked
 * for by name, so searching "live" while streaming never puts it under Enter.
 */
const keywords: Record<SearchAction, string[]> = {
    commit: ['commit', 'write a commit', 'new commit', 'compose'],
    feature: ['feature', 'request a feature', 'feature request'],
    bug: ['bug', 'report a bug', 'bug report'],
    'go-live': ['go live', 'live', 'stream', 'start stream'],
    'end-stream': ['end stream', 'stop stream', 'end live', 'stop live'],
};

/**
 * Two letters start too many names and topics for an action to be worth the top of the list.
 */
const shortestWord = 3;

/**
 * The actions a search offers, in the order they are listed. `/feature` and `/bug` work as they do in the composer,
 * from the slash on.
 */
export function searchActions(
    term: string,
    { isLive }: { isLive: boolean },
): SearchAction[] {
    const typed = term.trim().replace(/\s+/gu, ' ').toLowerCase();

    if (typed.startsWith('/')) {
        return (['feature', 'bug'] as const).filter((kind) =>
            `/${kind}`.startsWith(typed),
        );
    }

    if (typed.length < shortestWord) {
        return [];
    }

    const available: SearchAction[] = [
        'commit',
        'feature',
        'bug',
        isLive ? 'end-stream' : 'go-live',
    ];

    return available.filter((action) =>
        keywords[action].some((keyword) => keyword.startsWith(typed)),
    );
}
