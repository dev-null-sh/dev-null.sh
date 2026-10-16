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

    return linkCardFor(body, choice, setChoice, () => setChoice(undefined));
}

export function useLinkCards(bodies: string[]) {
    const [choices, setChoices] = useState<(string | null | undefined)[]>([]);

    const choose = (index: number, choice: string | null | undefined) =>
        setChoices((current) =>
            bodies.map((_, at) => (at === index ? choice : current[at])),
        );

    return {
        cards: bodies.map((body, index) =>
            linkCardFor(
                body,
                choices[index],
                (choice) => choose(index, choice),
                () => choose(index, undefined),
            ),
        ),
        insertPart: (index: number) =>
            setChoices((current) => [
                ...bodies.slice(0, index).map((_, at) => current[at]),
                undefined,
                ...bodies.slice(index).map((_, at) => current[index + at]),
            ]),
        removePart: (index: number) =>
            setChoices((current) =>
                bodies
                    .map((_, at) => current[at])
                    .filter((_, at) => at !== index),
            ),
        reset: () => setChoices([]),
    };
}

function linkCardFor(
    body: string,
    choice: string | null | undefined,
    choose: (link: string | null) => void,
    reset: () => void,
): UseLinkCardReturn {
    const links = typedLinks(body);
    const chosen =
        choice === null || (choice !== undefined && links.includes(choice))
            ? choice
            : undefined;

    return {
        links,
        link: chosen === undefined ? (links[0] ?? null) : chosen,
        isChosen: chosen !== undefined,
        choose,
        reset,
    };
}
