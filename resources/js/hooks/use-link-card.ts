import { useState } from 'react';
import { typedLinks } from '@/components/linkified-text';

export type UseLinkCardReturn = {
    readonly links: string[];
    readonly link: string | null;
    readonly isChosen: boolean;
    readonly choose: (link: string | null) => void;
    readonly reset: () => void;
};

/**
 * Keeps which link of a commit being written gets the link card: the first one, unless the author chose another
 * link that is still in the body, or no card at all. A composer that shares its draft passes the shared choice.
 */
export function useLinkCard(
    body: string,
    shared?: [
        string | null | undefined,
        (choice: string | null | undefined) => void,
    ],
): UseLinkCardReturn {
    const local = useState<string | null>();
    const [choice, setChoice] = shared ?? local;
    const links = typedLinks(body);
    const chosen =
        choice === null || (choice !== undefined && links.includes(choice))
            ? choice
            : undefined;

    return {
        links,
        link: chosen === undefined ? (links[0] ?? null) : chosen,
        isChosen: chosen !== undefined,
        choose: setChoice,
        reset: () => setChoice(undefined),
    };
}
