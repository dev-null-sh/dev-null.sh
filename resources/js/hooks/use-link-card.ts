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
 * link that is still in the body, or no card at all.
 */
export function useLinkCard(body: string): UseLinkCardReturn {
    const [choice, setChoice] = useState<string | null>();
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
