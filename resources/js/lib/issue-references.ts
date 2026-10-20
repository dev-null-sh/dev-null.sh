export type IssueReference = {
    number: number;
    title: string;
    url: string;
    kind: 'issue' | 'pull';
};

// The "#123" being typed right before the caret. Digits only, so "#abc" and markdown headings stay quiet.
const issueBeforeCaretPattern = /(?:^|[^\p{L}\p{N}_/#])#(\d{1,7})$/u;

/**
 * The issue or pull reference being typed right before the caret, when there is one.
 */
export function issueReferenceAt(
    value: string,
    caret: number,
): { start: number; query: string } | null {
    const before = value.slice(0, caret);
    const match = before.match(issueBeforeCaretPattern);

    if (!match) {
        return null;
    }

    return {
        start: caret - match[1].length - 1,
        query: match[1],
    };
}

/**
 * When GitHub only found one match for the exact number typed, insert its link without asking.
 */
export function shouldAutoLinkIssueReference(
    query: string,
    references: IssueReference[],
): IssueReference | null {
    if (references.length !== 1) {
        return null;
    }

    const [reference] = references;

    return String(reference.number) === query ? reference : null;
}
